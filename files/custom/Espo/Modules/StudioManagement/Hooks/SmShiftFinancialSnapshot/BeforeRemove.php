<?php

namespace Espo\Modules\StudioManagement\Hooks\SmShiftFinancialSnapshot;

use Espo\Core\Exceptions\Conflict;
use Espo\Core\Hook\Hook\BeforeRemove as BeforeRemoveHook;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\RemoveOptions;

/** @implements BeforeRemoveHook<Entity> */
class BeforeRemove implements BeforeRemoveHook
{
    public function beforeRemove(Entity $entity, RemoveOptions $options): void
    {
        throw new Conflict('Financial snapshots cannot be deleted.');
    }
}
