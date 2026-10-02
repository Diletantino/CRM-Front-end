<?php

namespace Espo\Modules\StudioManagement\Tools;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\NotFound;
use Espo\Entities\User;
use Espo\Modules\StudioManagement\Entities\SmShift;
use Espo\Modules\StudioManagement\Entities\SmShiftFinancialSnapshot;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use InvalidArgumentException;
use stdClass;

final class ShiftService
{
    public function __construct(
        private EntityManager $entityManager,
        private MoneyCalculator $money,
    ) {}

    public function open(string $id): Entity
    {
        return $this->entityManager->getTransactionManager()->run(function () use ($id): Entity {
            $shift = $this->getShiftForUpdate($id);

            if ($shift->get('status') !== SmShift::STATUS_DRAFT) {
                throw new Conflict('Only a draft shift can be opened.');
            }

            if (!$shift->get('teamId')) {
                throw new BadRequest('A team is required before opening the shift.');
            }

            $participantCount = $this->entityManager
                ->getRDBRepository(SmShift::ENTITY_TYPE)
                ->getRelation($shift, 'participants')
                ->count();

            if ($participantCount === 0) {
                throw new BadRequest('At least one participant is required.');
            }

            $now = gmdate('Y-m-d H:i:s');

            $shift->set([
                'name' => 'Shift ' . $now,
                'status' => SmShift::STATUS_OPEN,
                'startTime' => $now,
            ]);

            $this->entityManager->saveEntity($shift, ['skipBeforeSave' => true]);

            return $shift;
        });
    }

    public function close(
        string $id,
        string|int|float $gross,
        stdClass $platformMetrics,
        User $closedBy,
    ): Entity {
        $encodedMetrics = json_encode($platformMetrics, JSON_THROW_ON_ERROR);

        if (strlen($encodedMetrics) > 16384) {
            throw new BadRequest('Platform metrics must not exceed 16 KB.');
        }

        try {
            $grossCents = $this->money->amountToCents($gross);
        } catch (InvalidArgumentException $e) {
            throw new BadRequest($e->getMessage());
        }

        if ($grossCents <= 0) {
            throw new BadRequest('Gross income must be greater than zero.');
        }

        return $this->entityManager->getTransactionManager()->run(
            function () use ($id, $grossCents, $platformMetrics, $closedBy): Entity {
                $shift = $this->getShiftForUpdate($id);

                if ($shift->get('status') !== SmShift::STATUS_OPEN) {
                    throw new Conflict('Only an open shift can be closed.');
                }

                $teamId = $shift->get('teamId');
                $team = is_string($teamId) ? $this->entityManager->getEntityById('Team', $teamId) : null;

                if (!$team) {
                    throw new BadRequest('The shift team does not exist.');
                }

                $producerId = $team->get('smProducerId');
                $producer = is_string($producerId)
                    ? $this->entityManager->getEntityById('User', $producerId)
                    : null;

                if (
                    !$producer instanceof User ||
                    !$producer->isActive() ||
                    $producer->get('smAccountType') !== 'ProducerAdmin'
                ) {
                    throw new BadRequest('The team must have an active producer.');
                }

                $participants = $this->entityManager
                    ->getRDBRepository(SmShift::ENTITY_TYPE)
                    ->getRelation($shift, 'participants')
                    ->find();

                $breakdown = [];
                $participantCents = 0;
                $modelCents = 0;
                $operatorCents = 0;
                $percentUnitsTotal = 0;

                foreach ($participants as $participant) {
                    if (!$participant instanceof User || !$participant->isActive()) {
                        throw new BadRequest('Every shift participant must be an active user.');
                    }

                    $accountType = $participant->get('smAccountType');

                    if (!in_array($accountType, ['Model', 'Operator'], true)) {
                        throw new BadRequest('Every participant must be a model or operator.');
                    }

                    $percentUnits = $this->getEffectivePercentUnits($participant);
                    $shareCents = $this->money->share($grossCents, $percentUnits);
                    $participantCents += $shareCents;
                    $percentUnitsTotal += $percentUnits;

                    if ($accountType === 'Model') {
                        $modelCents += $shareCents;
                    } else {
                        $operatorCents += $shareCents;
                    }

                    $breakdown[] = [
                        'userId' => $participant->getId(),
                        'name' => (string) $participant->get('name'),
                        'accountType' => $accountType,
                        'percent' => $this->money->unitsToPercent($percentUnits),
                        'amount' => $this->money->centsToAmount($shareCents),
                    ];
                }

                $producerPercentUnits = $this->getEffectivePercentUnits($producer);
                $percentUnitsTotal += $producerPercentUnits;

                if ($percentUnitsTotal > 1_000_000) {
                    throw new BadRequest('The total participant and producer percentage exceeds 100%.');
                }

                $producerCents = $this->money->share($grossCents, $producerPercentUnits);
                $studioCents = $grossCents - $participantCents - $producerCents;
                $closedAt = gmdate('Y-m-d H:i:s');
                $currency = strtoupper((string) ($shift->get('currency') ?: 'USD'));

                $snapshot = $this->entityManager->getNewEntity(SmShiftFinancialSnapshot::ENTITY_TYPE);
                $snapshot->set([
                    'name' => 'Snapshot ' . (string) $shift->get('name'),
                    'shiftId' => $shift->getId(),
                    'teamId' => $team->getId(),
                    'producerId' => $producer->getId(),
                    'closedById' => $closedBy->getId(),
                    'closedAt' => $closedAt,
                    'currency' => $currency,
                    'totalGross' => $this->money->centsToAmount($grossCents),
                    'modelShare' => $this->money->centsToAmount($modelCents),
                    'operatorShare' => $this->money->centsToAmount($operatorCents),
                    'participantShare' => $this->money->centsToAmount($participantCents),
                    'producerShare' => $this->money->centsToAmount($producerCents),
                    'studioShare' => $this->money->centsToAmount($studioCents),
                    'participantBreakdown' => $breakdown,
                    'platformMetrics' => $platformMetrics,
                    'assignedUserId' => $producer->getId(),
                    'teamsIds' => [$team->getId()],
                ]);

                // The unique shiftId index makes this operation idempotent under concurrent requests.
                $this->entityManager->saveEntity($snapshot);

                $shift->set([
                    'status' => SmShift::STATUS_CLOSED,
                    'endTime' => $closedAt,
                    'closedById' => $closedBy->getId(),
                    'totalGross' => $this->money->centsToAmount($grossCents),
                    'modelShare' => $this->money->centsToAmount($modelCents),
                    'operatorShare' => $this->money->centsToAmount($operatorCents),
                    'producerShare' => $this->money->centsToAmount($producerCents),
                    'studioShare' => $this->money->centsToAmount($studioCents),
                    'platformMetrics' => $platformMetrics,
                ]);

                $this->entityManager->saveEntity($shift, ['skipBeforeSave' => true]);

                return $shift;
            }
        );
    }

    private function getShiftForUpdate(string $id): Entity
    {
        $shift = $this->entityManager
            ->getRDBRepository(SmShift::ENTITY_TYPE)
            ->where(['id' => $id])
            ->forUpdate()
            ->findOne();

        if (!$shift) {
            throw new NotFound('Shift not found.');
        }

        return $shift;
    }

    private function getEffectivePercentUnits(User $user): int
    {
        $override = $user->get('smSharePercentOverride');

        try {
            if ($override !== null && $override !== '') {
                return $this->money->percentToUnits($override);
            }

            $result = 0;
            $roles = $this->entityManager
                ->getRDBRepository('User')
                ->getRelation($user, 'roles')
                ->find();

            foreach ($roles as $role) {
                $result = max($result, $this->money->percentToUnits($role->get('smSharePercent')));
            }

            return $result;
        } catch (InvalidArgumentException $e) {
            throw new BadRequest($e->getMessage());
        }
    }
}
