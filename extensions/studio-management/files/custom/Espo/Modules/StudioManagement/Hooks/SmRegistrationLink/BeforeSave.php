<?php

namespace Espo\Modules\StudioManagement\Hooks\SmRegistrationLink;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Hook\Hook\BeforeSave as BeforeSaveHook;
use Espo\Entities\User;
use Espo\Modules\StudioManagement\Tools\RegistrationLinkUrl;
use Espo\Modules\StudioManagement\Tools\RegistrationToken;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/** @implements BeforeSaveHook<Entity> */
class BeforeSave implements BeforeSaveHook
{
    public function __construct(
        private EntityManager $entityManager,
        private RegistrationLinkUrl $registrationLinkUrl,
        private RegistrationToken $registrationToken,
        private User $user,
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if (!$entity->isNew()) {
            return;
        }

        if (!$this->user->isAdmin()) {
            throw new Forbidden('Only an administrator can create registration links.');
        }

        $roleId = $entity->get('targetRoleId');
        $accountType = $entity->get('accountType');

        if (!is_string($roleId) || $roleId === '') {
            throw new BadRequest('A target role is required.');
        }

        if (!in_array($accountType, ['Model', 'Operator', 'ProducerAdmin', 'TechnicalDirector', 'Recruiter'], true)) {
            throw new BadRequest('Unsupported account type.');
        }

        $role = $this->entityManager->getEntityById('Role', $roleId);

        if (!$role) {
            throw new BadRequest('Target role not found.');
        }

        if ($role->get('smAccountType') !== $accountType) {
            throw new BadRequest('Тип аккаунта выбранной роли должен совпадать с типом создаваемого аккаунта.');
        }

        $recruiterId = $entity->get('recruiterId');

        if (is_string($recruiterId) && $recruiterId !== '') {
            $recruiter = $this->entityManager->getEntityById('User', $recruiterId);

            if (
                !$recruiter ||
                !$recruiter->get('isActive') ||
                $recruiter->get('smAccountType') !== 'Recruiter'
            ) {
                throw new BadRequest('Выберите активного пользователя с типом аккаунта «Рекрутер» или оставьте поле пустым. Тип аккаунта задаётся в профиле пользователя.');
            }
        }

        $token = $this->registrationToken->generate();
        $expiresAt = $entity->get('expiresAt');

        if (!is_string($expiresAt) || $expiresAt === '') {
            $expiresAt = gmdate('Y-m-d H:i:s', time() + 86400);
        } elseif (strtotime($expiresAt . ' UTC') <= time()) {
            throw new BadRequest('Expiration must be in the future.');
        }

        $entity->set([
            'name' => sprintf('%s — %s', $accountType, (string) $role->get('name')),
            'token' => $token,
            'registrationUrl' => $this->registrationLinkUrl->build($token),
            'expiresAt' => $expiresAt,
            'isUsed' => false,
        ]);
    }
}
