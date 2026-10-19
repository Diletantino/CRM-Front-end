<?php

namespace Espo\Modules\StudioManagement\Authentication;

use Espo\Core\Api\Request;
use Espo\Core\Authentication\AuthenticationData;
use Espo\Core\Authentication\AuthToken\Manager as AuthTokenManager;
use Espo\Core\Authentication\Hook\BeforeLogin;
use Espo\Core\Exceptions\Forbidden;
use Espo\Entities\User;
use Espo\Modules\StudioManagement\Entities\SmRegistrationLink;
use Espo\Modules\StudioManagement\Tools\AccessPolicy;
use Espo\Modules\StudioManagement\Tools\RegistrationToken;
use Espo\ORM\EntityManager;

final class PersonalLinkGuard implements BeforeLogin
{
    private const HEADER_LINK_TOKEN = 'X-Studio-Access-Token';

    public function __construct(
        private EntityManager $entityManager,
        private AccessPolicy $accessPolicy,
        private AuthTokenManager $authTokenManager,
        private RegistrationToken $registrationToken,
    ) {}

    public function process(AuthenticationData $data, Request $request): void
    {
        $username = trim((string) $data->getUsername());

        if (str_starts_with($username, '**')) {
            return;
        }

        // Existing sessions do not bypass the administrator host/IP policy.
        if (
            $data->byTokenOnly() ||
            $request->getHeader('Espo-Authorization-By-Token') === 'true'
        ) {
            $sessionUser = $this->findSessionUser($data);

            if (
                $sessionUser?->isAdmin() &&
                !$this->accessPolicy->isAdminRequestAllowed($request)
            ) {
                throw new Forbidden('Administrator access is not allowed from this host or IP address.');
            }

            return;
        }

        $user = $username !== ''
            ? $this->entityManager->getRDBRepository('User')->where(['userName' => $username])->findOne()
            : null;

        if ($user instanceof User && $user->isAdmin()) {
            if ($this->accessPolicy->isAdminRequestAllowed($request)) {
                return;
            }

            throw new Forbidden('Administrator login is not allowed from this host or IP address.');
        }

        // EspoCRM performs one session check with a raw XMLHttpRequest that
        // sends username:auth-token but omits Espo-Authorization-By-Token.
        // Recognize only an active token belonging to the same user. EspoCRM
        // will still perform its normal token-secret and password-version checks.
        if ($user instanceof User && $this->isExistingSession($data, $user)) {
            return;
        }

        if (!$user instanceof User || !$user->isRegular()) {
            throw new Forbidden('A personal access link is required.');
        }

        $token = trim((string) $request->getHeader(self::HEADER_LINK_TOKEN));

        if (!$this->registrationToken->isValid($token)) {
            throw new Forbidden('A personal access link is required.');
        }

        $link = $this->entityManager
            ->getRDBRepository(SmRegistrationLink::ENTITY_TYPE)
            ->where([
                'token' => $token,
                'isUsed' => true,
                'usedByUserId' => $user->getId(),
            ])
            ->findOne();

        // Database collations may ignore case. The URL-safe secret must match byte for byte.
        if (!$link || !hash_equals((string) $link->get('token'), $token)) {
            throw new Forbidden('The personal access link does not match this account.');
        }
    }

    private function isExistingSession(AuthenticationData $data, User $user): bool
    {
        if ($data->getMethod() !== null) {
            return false;
        }

        $credential = $data->getPassword();

        if (!$credential) {
            return false;
        }

        $authToken = $this->authTokenManager->get($credential);

        return $authToken !== null &&
            $authToken->isActive() &&
            hash_equals($user->getId(), $authToken->getUserId());
    }

    private function findSessionUser(AuthenticationData $data): ?User
    {
        $credential = $data->getPassword();

        if (!$credential) {
            return null;
        }

        $authToken = $this->authTokenManager->get($credential);

        if ($authToken === null || !$authToken->isActive()) {
            return null;
        }

        $user = $this->entityManager->getEntityById('User', $authToken->getUserId());

        return $user instanceof User ? $user : null;
    }
}
