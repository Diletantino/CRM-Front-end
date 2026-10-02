<?php

namespace Espo\Modules\StudioManagement\Hooks\SmShift;

use Espo\Core\Exceptions\Conflict;
use Espo\Core\Hook\Hook\BeforeSave as BeforeSaveHook;
use Espo\Modules\StudioManagement\Entities\SmShift;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/** @implements BeforeSaveHook<Entity> */
class BeforeSave implements BeforeSaveHook
{
    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($entity->isNew()) {
            $entity->set('status', SmShift::STATUS_DRAFT);

            $teamId = $entity->get('teamId');

            if (is_string($teamId) && $teamId !== '') {
                $entity->set('teamsIds', [$teamId]);
            }

            return;
        }

        $previousStatus = $entity->getFetched('status');

        if ($previousStatus === SmShift::STATUS_DRAFT) {
            if ($entity->isAttributeChanged('teamId')) {
                $teamId = $entity->get('teamId');
                $entity->set('teamsIds', is_string($teamId) && $teamId !== '' ? [$teamId] : []);
            }

            return;
        }

        foreach (['name', 'teamId', 'participantsIds', 'currency', 'description'] as $attribute) {
            if ($entity->isAttributeChanged($attribute)) {
                throw new Conflict('An opened or closed shift cannot be edited.');
            }
        }
    }
}
