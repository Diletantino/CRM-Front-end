<?php

namespace Espo\Modules\StudioManagement\Hooks\SmRegistrationLink;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Hook\Hook\BeforeSave as BeforeSaveHook;
use Espo\Core\Utils\Config;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/** @implements BeforeSaveHook<Entity> */
class BeforeSave implements BeforeSaveHook
{
    public function __construct(
        private Config $config,
        private EntityManager $entityManager,
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if (!$entity->isNew()) {
            return;
        }

        $roleId = $entity->get('targetRoleId');
        $accountType = $entity->get('accountType');

        if (!is_string($roleId) || $roleId === '') {
            throw new BadRequest('A target role is required.');
        }

        if (!in_array($accountType, ['Model', 'Operator'], true)) {
            throw new BadRequest('Unsupported account type.');
        }

        $role = $this->entityManager->getEntityById('Role', $roleId);

        if (!$role) {
            throw new BadRequest('Target role not found.');
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = $entity->get('expiresAt');

        if (!is_string($expiresAt) || $expiresAt === '') {
            $expiresAt = gmdate('Y-m-d H:i:s', time() + 86400);
        } elseif (strtotime($expiresAt . ' UTC') <= time()) {
            throw new BadRequest('Expiration must be in the future.');
        }

        $siteUrl = rtrim((string) $this->config->get('siteUrl'), '/');

        if ($siteUrl === '') {
            throw new BadRequest('The EspoCRM siteUrl setting is required.');
        }

        $entity->set([
            'name' => sprintf('%s — %s', $accountType, (string) $role->get('name')),
            'token' => $token,
            'registrationUrl' => $siteUrl . '/?entryPoint=studioRegister&token=' . rawurlencode($token),
            'expiresAt' => $expiresAt,
            'isUsed' => false,
        ]);
    }
}
