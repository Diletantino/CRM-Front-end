<?php

namespace Espo\Modules\StudioManagement\Authentication;

use Espo\Core\Api\Request;
use Espo\Core\Authentication\AuthenticationData;
use Espo\Core\Authentication\AuthToken\Manager;
use Espo\Core\Authentication\Hook\BeforeLogin;
use Espo\Core\Exceptions\ServiceUnavailable;
use Espo\Entities\User;
use Espo\Modules\StudioManagement\Tools\AccountOnboarding;
use Espo\ORM\EntityManager;

final class PendingAccountGuard implements BeforeLogin
{
    public function __construct(private EntityManager $em, private Manager $tokens) {}

    public function process(AuthenticationData $data, Request $request): void
    {
        $username = trim((string) $data->getUsername());
        if ($data->getMethod() !== null || str_starts_with($username, '**')) {
            return;
        }
        if ($data->byTokenOnly()) {
            $credential = $data->getPassword();
            $token = $credential ? $this->tokens->get($credential) : null;
            if (!$token || !$token->isActive()) {
                return;
            }
            $identity = ['id' => $token->getUserId()];
        } elseif ($username !== '') {
            $identity = ['userName' => $username];
        } else {
            return;
        }
        $pending = $this->em->getRDBRepository(User::ENTITY_TYPE)->select(['id'])
            ->where($identity + [AccountOnboarding::PENDING => true])->findOne();
        if ($pending) {
            // Espo's auth response handler supports an explicit HTTP code on this exception.
            throw new ServiceUnavailable('Account registration is pending completion.', 502);
        }
    }
}
