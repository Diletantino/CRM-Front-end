<?php

namespace Espo\Modules\StudioManagement\Hooks\SmShift;

use Espo\Core\Exceptions\Conflict;
use Espo\Core\Hook\Hook\BeforeRemove as BeforeRemoveHook;
use Espo\Modules\StudioManagement\Entities\SmShift;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\RemoveOptions;

/** @implements BeforeRemoveHook<Entity> */
class BeforeRemove implements BeforeRemoveHook
{
    public function beforeRemove(Entity $entity, RemoveOptions $options): void
    {
        if ($entity->get('status') !== SmShift::STATUS_DRAFT) {
            throw new Conflict('Only draft shifts can be deleted.');
        }
    }
}
