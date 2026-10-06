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
    private const int MAX_SITE_ROWS = 50;
    private const int MAX_SCREENSHOTS = 50;

    public function __construct(
        private EntityManager $entityManager,
        private MoneyCalculator $money,
    ) {}

    public function open(string $id): Entity
    {
        return $this->entityManager->getTransactionManager()->run(function () use ($id): Entity {
            $shift = $this->getShiftForUpdate($id);

            if ($shift->get('status') !== SmShift::STATUS_DRAFT) {
                throw new Conflict('Only a preliminary shift can be started.');
            }

            if (!$shift->get('teamId')) {
                throw new BadRequest('A team is required before starting the shift.');
            }

            $model = $this->getParticipant($shift, 'modelId', 'Model');
            $operator = $this->getParticipant($shift, 'operatorId', 'Operator');
            [$siteData] = $this->normalizeSiteData((array) ($shift->get('siteData') ?? []));
            $now = gmdate('Y-m-d H:i:s');

            $shift->set([
                'name' => sprintf('Shift %s — %s', $now, (string) $model->get('name')),
                'status' => SmShift::STATUS_OPEN,
                'startTime' => $now,
                'siteData' => $siteData,
                'participantsIds' => [$model->getId(), $operator->getId()],
            ]);

            $this->entityManager->saveEntity($shift, ['skipBeforeSave' => true]);

            return $shift;
        });
    }

    public function finish(string $id): Entity
    {
        return $this->entityManager->getTransactionManager()->run(function () use ($id): Entity {
            $shift = $this->getShiftForUpdate($id);

            if ($shift->get('status') !== SmShift::STATUS_OPEN) {
                throw new Conflict('Only an active shift can be finished.');
            }

            $shift->set([
                'status' => SmShift::STATUS_COUNTING,
                'endTime' => gmdate('Y-m-d H:i:s'),
            ]);

            $this->entityManager->saveEntity($shift, ['skipBeforeSave' => true]);

            return $shift;
        });
    }

    /**
     * @param array<int, mixed> $siteData
     * @param array<int, mixed> $screenshotIds
     */
    public function close(string $id, array $siteData, array $screenshotIds, User $closedBy): Entity
    {
        return $this->entityManager->getTransactionManager()->run(
            function () use ($id, $siteData, $screenshotIds, $closedBy): Entity {
                $shift = $this->getShiftForUpdate($id);

                if ($shift->get('status') !== SmShift::STATUS_COUNTING) {
                    throw new Conflict('Only a shift being counted can be completed.');
                }

                $financial = $this->calculateFinancials($shift, $siteData, $closedBy);
                $closedAt = gmdate('Y-m-d H:i:s');
                $snapshot = $this->entityManager->getNewEntity(SmShiftFinancialSnapshot::ENTITY_TYPE);
                $snapshot->set(array_merge($financial['snapshot'], [
                    'name' => 'Snapshot ' . (string) $shift->get('name'),
                    'shiftId' => $shift->getId(),
                    'closedAt' => $closedAt,
                ]));

                $this->entityManager->saveEntity($snapshot);

                $shift->set(array_merge($financial['shift'], [
                    'status' => SmShift::STATUS_CLOSED,
                    'endTime' => $shift->get('endTime') ?: $closedAt,
                    'closedById' => $closedBy->getId(),
                    'screenshotsIds' => $this->normalizeAttachmentIds($screenshotIds),
                ]));

                $this->entityManager->saveEntity($shift, ['skipBeforeSave' => true]);

                return $shift;
            }
        );
    }

    /**
     * Privileged correction of a completed shift. The immutable snapshot can only be
     * changed through this transaction so the list totals and analytics stay aligned.
     *
     * @param array<int, mixed> $siteData
     * @param array<int, mixed> $screenshotIds
     */
    public function revise(string $id, array $siteData, array $screenshotIds, User $revisedBy): Entity
    {
        return $this->entityManager->getTransactionManager()->run(
            function () use ($id, $siteData, $screenshotIds, $revisedBy): Entity {
                $shift = $this->getShiftForUpdate($id);

                if ($shift->get('status') !== SmShift::STATUS_CLOSED) {
                    throw new Conflict('Only a completed shift can be revised.');
                }

                $financial = $this->calculateFinancials($shift, $siteData, $revisedBy);
                $snapshot = $this->entityManager
                    ->getRDBRepository(SmShiftFinancialSnapshot::ENTITY_TYPE)
                    ->where(['shiftId' => $shift->getId()])
                    ->forUpdate()
                    ->findOne();

                if (!$snapshot) {
                    throw new NotFound('Financial snapshot not found.');
                }

                $financial['snapshot']['closedById'] = $snapshot->get('closedById');
                $snapshot->set($financial['snapshot']);
                $this->entityManager->saveEntity($snapshot, ['skipBeforeSave' => true]);

                $shift->set(array_merge($financial['shift'], [
                    'screenshotsIds' => $this->normalizeAttachmentIds($screenshotIds),
                ]));
                $this->entityManager->saveEntity($shift, ['skipBeforeSave' => true]);

                return $shift;
            }
        );
    }

    /**
     * @param array<int, mixed> $siteData
     * @return array{shift: array<string, mixed>, snapshot: array<string, mixed>}
     */
    private function calculateFinancials(Entity $shift, array $siteData, User $closedBy): array
    {
        [$normalizedRows, $earnedCents, $bonusCents] = $this->normalizeSiteData($siteData);
        $grossCents = $earnedCents + $bonusCents;
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

        $participants = [
            $this->getParticipant($shift, 'modelId', 'Model'),
            $this->getParticipant($shift, 'operatorId', 'Operator'),
        ];
        $breakdown = [];
        $participantCents = 0;
        $modelCents = 0;
        $operatorCents = 0;
        $percentUnitsTotal = 0;

        foreach ($participants as $participant) {
            $percentUnits = $this->getEffectivePercentUnits($participant);
            $shareCents = $this->money->share($grossCents, $percentUnits);
            $participantCents += $shareCents;
            $percentUnitsTotal += $percentUnits;

            if ($participant->get('smAccountType') === 'Model') {
                $modelCents += $shareCents;
            } else {
                $operatorCents += $shareCents;
            }

            $breakdown[] = [
                'userId' => $participant->getId(),
                'name' => (string) $participant->get('name'),
                'accountType' => (string) $participant->get('smAccountType'),
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
        $currency = strtoupper((string) ($shift->get('currency') ?: 'USD'));
        $platformMetrics = (object) [
            'sites' => array_map(
                static fn (array $row): array => [
                    'site' => $row['site'],
                    'state' => $row['state'],
                    'earning' => $row['earning'],
                    'offlineBonus' => $row['offlineBonus'],
                ],
                $normalizedRows,
            ),
        ];
        $common = [
            'currency' => $currency,
            'totalGross' => $this->money->centsToAmount($grossCents),
            'modelShare' => $this->money->centsToAmount($modelCents),
            'operatorShare' => $this->money->centsToAmount($operatorCents),
            'producerShare' => $this->money->centsToAmount($producerCents),
            'studioShare' => $this->money->centsToAmount($studioCents),
            'platformMetrics' => $platformMetrics,
        ];

        return [
            'shift' => array_merge($common, [
                'siteData' => $normalizedRows,
                'calculatedTotal' => $this->money->centsToAmount($earnedCents),
                'offlineBonus' => $this->money->centsToAmount($bonusCents),
                'result' => $this->money->centsToAmount($grossCents),
            ]),
            'snapshot' => array_merge($common, [
                'teamId' => $team->getId(),
                'producerId' => $producer->getId(),
                'closedById' => $closedBy->getId(),
                'participantShare' => $this->money->centsToAmount($participantCents),
                'participantBreakdown' => $breakdown,
                'assignedUserId' => $producer->getId(),
                'teamsIds' => [$team->getId()],
            ]),
        ];
    }

    /**
     * @param array<int, mixed> $rows
     * @return array{0: array<int, array<string, string>>, 1: int, 2: int}
     */
    private function normalizeSiteData(array $rows): array
    {
        if ($rows === [] || count($rows) > self::MAX_SITE_ROWS) {
            throw new BadRequest('Provide between 1 and 50 site access rows.');
        }

        $normalized = [];
        $earnedCents = 0;
        $bonusCents = 0;

        foreach ($rows as $row) {
            if ($row instanceof stdClass) {
                $row = (array) $row;
            }

            if (!is_array($row)) {
                throw new BadRequest('Every site row must be an object.');
            }

            $siteValue = $row['site'] ?? '';
            $loginValue = $row['login'] ?? '';
            $passwordValue = $row['password'] ?? '';
            $stateValue = $row['state'] ?? 'Pending';
            $earningValue = $row['earning'] ?? 0;
            $bonusValue = $row['offlineBonus'] ?? 0;

            if (
                !is_string($siteValue) ||
                !is_string($loginValue) ||
                !is_string($passwordValue) ||
                !is_string($stateValue) ||
                (!is_string($earningValue) && !is_int($earningValue) && !is_float($earningValue)) ||
                (!is_string($bonusValue) && !is_int($bonusValue) && !is_float($bonusValue))
            ) {
                throw new BadRequest('Site row fields have invalid types.');
            }

            $site = trim($siteValue);
            $login = trim($loginValue);
            $password = $passwordValue;
            $state = $stateValue;

            if ($site === '' || $login === '' || $password === '' || mb_strlen($site) > 80) {
                throw new BadRequest('Site, login and password are required for every access row.');
            }

            if (mb_strlen($login) > 150 || mb_strlen($password) > 500) {
                throw new BadRequest('Site credentials are too long.');
            }

            if (!in_array($state, ['Pending', 'Started', 'Banned'], true)) {
                throw new BadRequest('Unknown site state.');
            }

            try {
                $earning = $this->money->amountToCents($earningValue);
                $bonus = $this->money->amountToCents($bonusValue);
            } catch (InvalidArgumentException $e) {
                throw new BadRequest($e->getMessage());
            }

            $earnedCents += $earning;
            $bonusCents += $bonus;
            $normalized[] = [
                'site' => $site,
                'login' => $login,
                'password' => $password,
                'state' => $state,
                'earning' => $this->money->centsToAmount($earning),
                'offlineBonus' => $this->money->centsToAmount($bonus),
            ];
        }

        return [$normalized, $earnedCents, $bonusCents];
    }

    /** @param array<int, mixed> $ids @return array<int, string> */
    private function normalizeAttachmentIds(array $ids): array
    {
        $result = [];

        foreach ($ids as $id) {
            if (!is_string($id) || !preg_match('/^[a-zA-Z0-9_-]{1,36}$/', $id)) {
                throw new BadRequest('Invalid screenshot attachment ID.');
            }

            $result[$id] = $id;
        }

        if (count($result) > self::MAX_SCREENSHOTS) {
            throw new BadRequest('No more than 50 screenshots are allowed.');
        }

        return array_values($result);
    }

    private function getParticipant(Entity $shift, string $field, string $expectedType): User
    {
        $id = $shift->get($field);
        $participant = is_string($id) ? $this->entityManager->getEntityById('User', $id) : null;

        if (
            !$participant instanceof User ||
            !$participant->isActive() ||
            $participant->get('smAccountType') !== $expectedType
        ) {
            throw new BadRequest(sprintf('The shift must have an active %s.', strtolower($expectedType)));
        }

        return $participant;
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
