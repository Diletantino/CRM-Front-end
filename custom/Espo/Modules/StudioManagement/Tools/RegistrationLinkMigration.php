<?php

namespace Espo\Modules\StudioManagement\Tools;

use Espo\Modules\StudioManagement\Entities\SmRegistrationLink;
use Espo\ORM\EntityManager;

final class RegistrationLinkMigration
{
    public function __construct(
        private EntityManager $entityManager,
        private RegistrationLinkUrl $registrationLinkUrl,
    ) {}

    public function run(): int
    {
        $count = 0;
        $links = $this->entityManager
            ->getRDBRepository(SmRegistrationLink::ENTITY_TYPE)
            ->find();

        foreach ($links as $link) {
            $token = (string) $link->get('token');

            if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
                continue;
            }

            $url = $this->registrationLinkUrl->build($token);

            if ($link->get('registrationUrl') === $url) {
                continue;
            }

            $link->set('registrationUrl', $url);
            $this->entityManager->saveEntity($link, ['skipBeforeSave' => true]);
            $count++;
        }

        return $count;
    }
}
