<?php

namespace Espo\Modules\StudioManagement\Tools;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Entities\User;
use Espo\Modules\StudioManagement\Entities\SmShift;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

final class StaffService
{
    private const int HISTORY_LIMIT = 100;

    public function __construct(private EntityManager $entityManager, private RolePolicy $policy) {}

    /** @return array{type: string, rows: array<int, array<string, mixed>>} */
    public function getList(string $type, User $viewer): array
    {
        [$accountType, $linkField] = $this->resolveType($type);
        [$users, $teamNames] = $this->getAccessibleUsers($accountType, $viewer);
        if ($this->policy->profileLevel($viewer) !== 'all') {
            return ['type' => $type, 'rows' => array_map(static fn (User $user): array => [
                'id' => $user->getId(), 'name' => $user->getUserName(),
                'hasAvatar' => (bool) $user->get('avatarId'),
            ], array_values($users))];
        }
        $stats = $this->getListStats($linkField, array_keys($users));
        $rows = [];

        uasort($users, static fn (User $a, User $b): int => strnatcasecmp(
            (string) $a->get('name'),
            (string) $b->get('name'),
        ));

        foreach ($users as $id => $user) {
            $userStats = $stats[$id] ?? $this->emptyStats();
            $rows[] = [
                'id' => $id,
                'name' => (string) $user->get('name'),
                'birthDate' => $user->get('smBirthDate'),
                'startDate' => $user->get('smStartDate'),
                'contact' => (string) ($user->get('smContact') ?: $user->get('phoneNumber') ?: ''),
                'hasAvatar' => (bool) $user->get('avatarId'),
                'teamNames' => array_values($teamNames[$id] ?? []),
                'previousWeekAverage' => $this->average(
                    $userStats['previousWeekTotal'],
                    $userStats['previousWeekCount'],
                ),
                'currentWeekAverage' => $this->average(
                    $userStats['currentWeekTotal'],
                    $userStats['currentWeekCount'],
                ),
                'currentWeekShiftCount' => $userStats['currentWeekCount'],
                'lastShiftAt' => $userStats['lastShiftAt'],
            ];
        }

        return ['type' => $type, 'rows' => $rows];
    }

    /** @return array<string, mixed> */
    public function getProfile(string $type, string $id, User $viewer): array
    {
        if ($this->policy->profileLevel($viewer) !== 'all') throw new Forbidden();
        [$accountType, $linkField] = $this->resolveType($type);
        $user = $this->getAccessibleUser($id, $accountType, $viewer);
        $query = $this->entityManager
            ->getQueryBuilder()
            ->select()
            ->from(SmShift::ENTITY_TYPE)
            ->select([
                'id',
                'number',
                'teamId',
                'operatorId',
                'modelId',
                'startTime',
                'endTime',
                'result',
                'modelShare',
                'operatorShare',
                'currency',
            ])
            ->where([
                'deleted' => false,
                'status' => SmShift::STATUS_CLOSED,
                $linkField => $id,
            ])
            ->order('endTime', 'DESC')
            ->limit(0, self::HISTORY_LIMIT)
            ->build();
        $rawRows = $this->entityManager->getQueryExecutor()->execute($query)->fetchAll() ?: [];
        $history = [];
        $earningsByDate = [];
        $entityNameCache = [];
        $shareField = $accountType === 'Model' ? 'modelShare' : 'operatorShare';

        foreach ($rawRows as $row) {
            $endTime = (string) ($row['endTime'] ?? '');
            $date = substr($endTime, 0, 10);
            $share = (float) ($row[$shareField] ?? 0);

            if ($date !== '') {
                $earningsByDate[$date] = ($earningsByDate[$date] ?? 0.0) + $share;
            }

            $teamId = (string) ($row['teamId'] ?? '');
            $operatorId = (string) ($row['operatorId'] ?? '');
            $history[] = [
                'id' => (string) $row['id'],
                'number' => (int) ($row['number'] ?? 0),
                'teamName' => $this->getEntityName('Team', $teamId, $entityNameCache),
                'operatorName' => $this->getEntityName('User', $operatorId, $entityNameCache),
                'startTime' => $row['startTime'] ?? null,
                'endTime' => $row['endTime'] ?? null,
                'result' => number_format((float) ($row['result'] ?? 0), 2, '.', ''),
                'share' => number_format($share, 2, '.', ''),
                'currency' => (string) ($row['currency'] ?? 'USD'),
            ];
        }

        ksort($earningsByDate);
        $chart = [];

        foreach ($earningsByDate as $date => $amount) {
            $chart[] = ['date' => $date, 'amount' => number_format($amount, 2, '.', '')];
        }

        return [
            'id' => $user->getId(),
            'type' => $type,
            'accountType' => $accountType,
            'history' => $history,
            'chart' => $chart,
        ];
    }

    /** @return array<int, array<string, string>> */
    /** @return array{0: string, 1: string} */
    private function resolveType(string $type): array
    {
        return match ($type) {
            'model' => ['Model', 'modelId'],
            'operator' => ['Operator', 'operatorId'],
            default => throw new BadRequest('Unknown staff type.'),
        };
    }

    /**
     * @return array{0: array<string, User>, 1: array<string, array<string, string>>}
     */
    private function getAccessibleUsers(string $accountType, User $viewer): array
    {
        $this->assertViewerRole($viewer);
        $users = [];
        $teamNames = [];

        if ($this->policy->staffLevel($viewer) === 'all') {
            $collection = $this->entityManager
                ->getRDBRepository(User::ENTITY_TYPE)
                ->where(['isActive' => true, 'smAccountType' => $accountType])
                ->find();

            foreach ($collection as $user) {
                if (!$user instanceof User) {
                    continue;
                }

                $users[$user->getId()] = $user;
                $teams = $this->entityManager
                    ->getRDBRepository(User::ENTITY_TYPE)
                    ->getRelation($user, 'teams')
                    ->find();

                foreach ($teams as $team) {
                    $teamNames[$user->getId()][$team->getId()] = (string) $team->get('name');
                }
            }

            return [$users, $teamNames];
        }

        $teams = $this->entityManager
            ->getRDBRepository('Team')
            ->where(['smProducerId' => $viewer->getId()])
            ->find();

        foreach ($teams as $team) {
            $members = $this->entityManager
                ->getRDBRepository('Team')
                ->getRelation($team, 'users')
                ->find();

            foreach ($members as $member) {
                if (
                    !$member instanceof User ||
                    !$member->isActive() ||
                    $member->get('smAccountType') !== $accountType
                ) {
                    continue;
                }

                $users[$member->getId()] = $member;
                $teamNames[$member->getId()][$team->getId()] = (string) $team->get('name');
            }
        }

        return [$users, $teamNames];
    }

    private function getAccessibleUser(string $id, string $accountType, User $viewer): User
    {
        $this->assertViewerRole($viewer);

        if ($this->policy->staffLevel($viewer) === 'all') {
            $user = $this->entityManager->getEntityById(User::ENTITY_TYPE, $id);

            if (
                $user instanceof User &&
                $user->isActive() &&
                $user->get('smAccountType') === $accountType
            ) {
                return $user;
            }

            throw new NotFound('Staff profile not found.');
        }

        [$users] = $this->getAccessibleUsers($accountType, $viewer);
        $user = $users[$id] ?? null;

        if (!$user instanceof User) {
            throw new NotFound('Staff profile not found.');
        }

        return $user;
    }

    private function assertViewerRole(User $viewer): void
    {
        if ($this->policy->staffLevel($viewer) === 'no') {
            throw new Forbidden('Your role cannot access staff profiles.');
        }
    }

    /**
     * @param array<int, string> $ids
     * @return array<string, array<string, mixed>>
     */
    private function getListStats(string $linkField, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $utc = new DateTimeZone('UTC');
        $currentStart = (new DateTimeImmutable('now', $utc))->modify('monday this week')->setTime(0, 0);
        $previousStart = $currentStart->sub(new DateInterval('P7D'));
        $nextStart = $currentStart->add(new DateInterval('P7D'));
        $stats = [];
        $recentQuery = $this->entityManager
            ->getQueryBuilder()
            ->select()
            ->from(SmShift::ENTITY_TYPE)
            ->select([$linkField, 'result', 'endTime'])
            ->where([
                'deleted' => false,
                'status' => SmShift::STATUS_CLOSED,
                $linkField => $ids,
                'endTime>=' => $previousStart->format('Y-m-d H:i:s'),
                'endTime<' => $nextStart->format('Y-m-d H:i:s'),
            ])
            ->build();
        $recentRows = $this->entityManager->getQueryExecutor()->execute($recentQuery)->fetchAll() ?: [];

        foreach ($recentRows as $row) {
            $id = (string) ($row[$linkField] ?? '');

            if ($id === '') {
                continue;
            }

            $stats[$id] ??= $this->emptyStats();
            $endTime = (string) ($row['endTime'] ?? '');
            $amount = (float) ($row['result'] ?? 0);

            if ($endTime >= $currentStart->format('Y-m-d H:i:s')) {
                $stats[$id]['currentWeekTotal'] += $amount;
                $stats[$id]['currentWeekCount']++;
            } else {
                $stats[$id]['previousWeekTotal'] += $amount;
                $stats[$id]['previousWeekCount']++;
            }
        }

        $lastQuery = $this->entityManager
            ->getQueryBuilder()
            ->select()
            ->from(SmShift::ENTITY_TYPE)
            ->select([$linkField, ['MAX:endTime', 'lastShiftAt']])
            ->where([
                'deleted' => false,
                'status' => SmShift::STATUS_CLOSED,
                $linkField => $ids,
            ])
            ->group([$linkField])
            ->build();
        $lastRows = $this->entityManager->getQueryExecutor()->execute($lastQuery)->fetchAll() ?: [];

        foreach ($lastRows as $row) {
            $id = (string) ($row[$linkField] ?? '');

            if ($id === '') {
                continue;
            }

            $stats[$id] ??= $this->emptyStats();
            $stats[$id]['lastShiftAt'] = $row['lastShiftAt'] ?? null;
        }

        return $stats;
    }

    /** @return array<string, mixed> */
    private function emptyStats(): array
    {
        return [
            'previousWeekTotal' => 0.0,
            'previousWeekCount' => 0,
            'currentWeekTotal' => 0.0,
            'currentWeekCount' => 0,
            'lastShiftAt' => null,
        ];
    }

    private function average(float $total, int $count): string
    {
        return number_format($count > 0 ? $total / $count : 0, 2, '.', '');
    }

    /** @param array<string, string> $cache */
    private function getEntityName(string $entityType, string $id, array &$cache): string
    {
        if ($id === '') {
            return '—';
        }

        $key = $entityType . ':' . $id;

        if (!array_key_exists($key, $cache)) {
            $entity = $this->entityManager->getEntityById($entityType, $id);
            $cache[$key] = $entity instanceof Entity ? (string) $entity->get('name') : '—';
        }

        return $cache[$key];
    }

}
