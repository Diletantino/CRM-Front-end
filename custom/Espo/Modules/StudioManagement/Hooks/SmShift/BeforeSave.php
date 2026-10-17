<?php

namespace Espo\Modules\StudioManagement\Hooks\SmShift;

use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Hook\Hook\BeforeSave as BeforeSaveHook;
use Espo\Entities\User;
use Espo\Modules\StudioManagement\Entities\SmShift;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Modules\StudioManagement\Tools\ModelShiftDefaults;
use Espo\ORM\Repository\Option\SaveOptions;

/** @implements BeforeSaveHook<Entity> */
class BeforeSave implements BeforeSaveHook
{
    public function __construct(
        private User $user,
        private EntityManager $entityManager,
        private ModelShiftDefaults $defaults,
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($entity->isNew()) {
            $entity->set('status', SmShift::STATUS_DRAFT);
            $this->prepareDraft($entity);

            $this->syncRelations($entity);

            return;
        }

        $previousStatus = $entity->getFetched('status');

        if ($previousStatus === SmShift::STATUS_DRAFT) {
            if ($entity->isAttributeChanged('status') || $entity->isAttributeChanged('endTime')) {
                throw new Conflict('Статус смены изменяется только кнопками запуска и завершения.');
            }
            $this->prepareDraft($entity);
            $this->syncRelations($entity);

            return;
        }

        if ($previousStatus === SmShift::STATUS_OPEN && $entity->isAttributeChanged('siteData')) {
            if (
                !$this->user->isAdmin() &&
                (
                    !in_array($this->user->get('smAccountType'), ['Operator', 'ProducerAdmin'], true) ||
                    $entity->get('operatorId') !== $this->user->getId()
                )
            ) {
                throw new Forbidden('Only the assigned operator can update site states.');
            }
            $oldRows = (array) ($entity->getFetched('siteData') ?? []);
            $newRows = (array) ($entity->get('siteData') ?? []);
            if (count($oldRows) !== count($newRows)) {
                throw new Conflict('Во время смены можно менять только состояние сайтов.');
            }
            foreach ($newRows as $index => $row) {
                $row = (array) $row;
                $old = (array) ($oldRows[$index] ?? []);
                if (!in_array($row['state'] ?? null, ['Pending', 'Started', 'Banned'], true)) {
                    throw new BadRequest('Некорректное состояние сайта.');
                }
                unset($row['state'], $old['state']);
                if ($row != $old) {
                    throw new Conflict('Во время смены можно менять только состояние сайтов.');
                }
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
            'modelShare',
            'operatorShare',
            'producerShare',
            'technicalDirectorShare',
            'recruiterShare',
            'expensesTotal',
            'studioShare',
            'expenseBreakdown',
            'platformMetrics',
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
        $entity->set('assignedUserId', $entity->get('operatorId'));
    }

    private function prepareDraft(Entity $entity): void
    {
        if ($entity->get('startTime')) {
            $value = (string) $entity->get('startTime');
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new \DateTimeZone('UTC'));
            if (!$date || $date->format('Y-m-d H:i:s') !== $value) {
                throw new BadRequest('Укажите корректные дату и время начала смены.');
            }
            $entity->set('startTime', $date->format('Y-m-d H:i:00'));
        }
        if (($entity->isNew() && !$entity->get('siteData')) ||
            (!$entity->isNew() && $entity->isAttributeChanged('modelId'))) {
            $modelId = $entity->get('modelId');
            $model = $modelId ? $this->entityManager->getEntityById('User', $modelId) : null;
            if ($model) {
                $entity->set($this->defaults->get($model));
            }
        }
    }
}
