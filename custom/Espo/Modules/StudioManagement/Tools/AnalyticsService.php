<?php

namespace Espo\Modules\StudioManagement\Tools;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\Exceptions\BadRequest;
use Espo\Entities\User;
use Espo\Modules\StudioManagement\Entities\SmShiftFinancialSnapshot;
use Espo\ORM\EntityManager;

final class AnalyticsService
{
    public function __construct(private EntityManager $entityManager) {}

    public function get(string $fromRaw, string $toRaw, User $user): array
    {
        $utc = new DateTimeZone('UTC');
        $from = DateTimeImmutable::createFromFormat('!Y-m-d', $fromRaw, $utc);
        $toInclusive = DateTimeImmutable::createFromFormat('!Y-m-d', $toRaw, $utc);

        if (!$from || !$toInclusive || $from->format('Y-m-d') !== $fromRaw || $toInclusive->format('Y-m-d') !== $toRaw) {
            throw new BadRequest('Dates must use the YYYY-MM-DD format.');
        }

        $to = $toInclusive->add(new DateInterval('P1D'));

        if ($from >= $to || $from->diff($to)->days > 732) {
            throw new BadRequest('The date range must be between 1 day and 2 years.');
        }

        $where = [
            'deleted' => false,
            'closedAt>=' => $from->format('Y-m-d H:i:s'),
            'closedAt<' => $to->format('Y-m-d H:i:s'),
        ];

        // Producer accounts are restricted to their own immutable snapshots even
        // when a role was accidentally configured with broad entity read access.
        if (!$user->isAdmin() && $user->get('smAccountType') === 'ProducerAdmin') {
            $where['producerId'] = $user->getId();
        }

        $query = $this->entityManager
            ->getQueryBuilder()
            ->select()
            ->from(SmShiftFinancialSnapshot::ENTITY_TYPE)
            ->select([
                'producerId',
                'teamId',
                'currency',
                ['COUNT:(id)', 'shiftCount'],
                ['SUM:totalGross', 'totalGross'],
                ['SUM:modelShare', 'modelShare'],
                ['SUM:operatorShare', 'operatorShare'],
                ['SUM:participantShare', 'participantShare'],
                ['SUM:producerShare', 'producerShare'],
                ['SUM:studioShare', 'studioShare'],
            ])
            ->where($where)
            ->group(['producerId', 'teamId', 'currency'])
            ->order(['currency', 'producerId', 'teamId'])
            ->build();

        $sth = $this->entityManager->getQueryExecutor()->execute($query);
        $rawRows = $sth->fetchAll() ?: [];
        $rows = [];
        $totalsByCurrency = [];

        foreach ($rawRows as $raw) {
            $currency = (string) $raw['currency'];
            $producer = $this->entityManager->getEntityById('User', (string) $raw['producerId']);
            $team = $this->entityManager->getEntityById('Team', (string) $raw['teamId']);

            $row = [
                'producerId' => (string) $raw['producerId'],
                'producerName' => $producer ? (string) $producer->get('name') : '—',
                'teamId' => (string) $raw['teamId'],
                'teamName' => $team ? (string) $team->get('name') : '—',
                'currency' => $currency,
                'shiftCount' => (int) $raw['shiftCount'],
                'totalGross' => (string) ($raw['totalGross'] ?? '0'),
                'modelShare' => (string) ($raw['modelShare'] ?? '0'),
                'operatorShare' => (string) ($raw['operatorShare'] ?? '0'),
                'participantShare' => (string) ($raw['participantShare'] ?? '0'),
                'producerShare' => (string) ($raw['producerShare'] ?? '0'),
                'studioShare' => (string) ($raw['studioShare'] ?? '0'),
            ];
            $rows[] = $row;

            $totalsByCurrency[$currency] ??= [
                'currency' => $currency,
                'shiftCount' => 0,
                'totalGross' => 0.0,
                'participantShare' => 0.0,
                'producerShare' => 0.0,
                'studioShare' => 0.0,
            ];
            $totalsByCurrency[$currency]['shiftCount'] += $row['shiftCount'];
            $totalsByCurrency[$currency]['totalGross'] += (float) $row['totalGross'];
            $totalsByCurrency[$currency]['participantShare'] += (float) $row['participantShare'];
            $totalsByCurrency[$currency]['producerShare'] += (float) $row['producerShare'];
            $totalsByCurrency[$currency]['studioShare'] += (float) $row['studioShare'];
        }

        foreach ($totalsByCurrency as &$total) {
            foreach (['totalGross', 'participantShare', 'producerShare', 'studioShare'] as $field) {
                $total[$field] = number_format((float) $total[$field], 2, '.', '');
            }
        }
        unset($total);

        return [
            'from' => $fromRaw,
            'to' => $toRaw,
            'totals' => array_values($totalsByCurrency),
            'rows' => $rows,
        ];
    }
}
