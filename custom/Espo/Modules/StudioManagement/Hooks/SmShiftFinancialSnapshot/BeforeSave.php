<?php

namespace Espo\Modules\StudioManagement\Hooks\SmShiftFinancialSnapshot;

use Espo\Core\Exceptions\Conflict;
use Espo\Core\Hook\Hook\BeforeSave as BeforeSaveHook;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/** @implements BeforeSaveHook<Entity> */
class BeforeSave implements BeforeSaveHook
{
    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if (!$entity->isNew()) {
            throw new Conflict('Financial snapshots are immutable.');
        }
    }
}
