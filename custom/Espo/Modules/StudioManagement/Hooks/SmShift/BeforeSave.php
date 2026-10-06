<?php

namespace Espo\Modules\StudioManagement\Hooks\SmShift;

use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Hook\Hook\BeforeSave as BeforeSaveHook;
use Espo\Entities\User;
use Espo\Modules\StudioManagement\Entities\SmShift;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/** @implements BeforeSaveHook<Entity> */
class BeforeSave implements BeforeSaveHook
{
    public function __construct(private User $user) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($entity->isNew()) {
            $entity->set('status', SmShift::STATUS_DRAFT);

            $this->syncRelations($entity);

            return;
        }

        $previousStatus = $entity->getFetched('status');

        if ($previousStatus === SmShift::STATUS_DRAFT) {
            $this->syncRelations($entity);

            return;
        }

        if ($previousStatus === SmShift::STATUS_OPEN && $entity->isAttributeChanged('siteData')) {
            if (
                !$this->user->isAdmin() &&
                (
                    $this->user->get('smAccountType') !== 'Operator' ||
                    $entity->get('operatorId') !== $this->user->getId()
                )
            ) {
                throw new Forbidden('Only the assigned operator can update site states.');
            }
        }

        foreach ([
            'name',
            'status',
            'startTime',
            'endTime',
            'teamId',
            'operatorId',
            'modelId',
            'modelBirthDate',
            'modelImagesIds',
            'participantsIds',
            'currency',
            'description',
            'screenshotsIds',
            'botTotal',
            'calculatedTotal',
            'offlineBonus',
            'result',
            'totalGross',
            'closedById',
            'financialSnapshotId',
        ] as $attribute) {
            if ($entity->isAttributeChanged($attribute)) {
                throw new Conflict('This shift stage can only be changed through the shift workflow.');
            }
        }

        if ($previousStatus !== SmShift::STATUS_OPEN && $entity->isAttributeChanged('siteData')) {
            throw new Conflict('Calculation rows can only be changed through the shift workflow.');
        }
    }

    private function syncRelations(Entity $entity): void
    {
        $teamId = $entity->get('teamId');
        $entity->set('teamsIds', is_string($teamId) && $teamId !== '' ? [$teamId] : []);

        $participantIds = [];

        foreach (['modelId', 'operatorId'] as $field) {
            $id = $entity->get($field);

            if (is_string($id) && $id !== '') {
                $participantIds[$id] = $id;
            }
        }

        $entity->set('participantsIds', array_values($participantIds));
    }
}
