<?php

namespace Espo\Modules\StudioManagement\Hooks\SmRegistrationLink;

use Espo\Core\Hook\Hook\BeforeSave as BeforeSaveHook;
use Espo\Entities\User;
use Espo\Modules\StudioManagement\Tools\RegistrationLinkUrl;
use Espo\Modules\StudioManagement\Tools\RegistrationToken;
use Espo\Modules\StudioManagement\Tools\WorkflowError;
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
        private \Espo\Modules\StudioManagement\Tools\RolePolicy $policy,
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if (!$entity->isNew()) {
            if (
                !$entity->isAttributeChanged('expiresAt') &&
                !$entity->isAttributeChanged('neverExpires')
            ) {
                return;
            }

            $this->applyExpiration($entity);

            return;
        }

        if (!$this->policy->canInvite($this->user)) {
            throw WorkflowError::forbidden('У вашей роли нет права создавать ссылки регистрации.');
        }

        $roleId = $entity->get('targetRoleId');
        $accountType = $entity->get('accountType');

        if (!is_string($roleId) || $roleId === '') {
            throw WorkflowError::badRequest('Выберите роль для нового аккаунта.');
        }

        if (!in_array($accountType, ['Model', 'Operator', 'ProducerAdmin', 'TechnicalDirector', 'Recruiter'], true)) {
            throw WorkflowError::badRequest('Выберите поддерживаемый тип аккаунта.');
        }

        $role = $this->entityManager->getEntityById('Role', $roleId);

        if (!$role) {
            throw WorkflowError::badRequest('Выбранная роль не найдена. Выберите другую роль.');
        }

        $recruiterId = $entity->get('recruiterId');

        if (is_string($recruiterId) && $recruiterId !== '') {
            $recruiter = $this->entityManager->getEntityById('User', $recruiterId);

            if (
                !$recruiter ||
                !$recruiter->get('isActive') ||
                $recruiter->get('smAccountType') !== 'Recruiter'
            ) {
                throw WorkflowError::badRequest('Выберите активного пользователя с типом аккаунта «Рекрутер» или оставьте поле пустым. Тип аккаунта задаётся в профиле пользователя.');
            }
        }

        $token = $this->registrationToken->generate();
        $this->applyExpiration($entity);

        $entity->set([
            'name' => sprintf('%s — %s', $accountType, (string) $role->get('name')),
            'token' => $token,
            'registrationUrl' => $this->registrationLinkUrl->build($token),
            'isUsed' => false,
        ]);
    }

    private function applyExpiration(Entity $entity): void
    {
        $neverExpires = (bool) $entity->get('neverExpires');
        $expiresAt = $entity->get('expiresAt');

        if ($neverExpires) {
            $expiresAt = '2200-01-01 00:00:00';
        } elseif (
            !is_string($expiresAt) ||
            $expiresAt === '' ||
            $expiresAt === '2200-01-01 00:00:00'
        ) {
            $expiresAt = gmdate('Y-m-d H:i:s', time() + 86400);
        } elseif (strtotime($expiresAt . ' UTC') <= time()) {
            throw WorkflowError::badRequest('Срок действия ссылки должен быть в будущем.');
        }

        $entity->set([
            'expiresAt' => $expiresAt,
            'neverExpires' => $neverExpires,
        ]);
    }
}
