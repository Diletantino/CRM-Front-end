<?php

namespace Espo\Modules\StudioManagement\Tools;

use DateTimeImmutable;
use Espo\Entities\User;
use Espo\Modules\StudioManagement\Entities\SmShift;
use Espo\Modules\StudioManagement\Entities\SmShiftExpense;
use Espo\ORM\EntityManager;
use Espo\ORM\Query\Part\Expression as Expr;

final class PersonalAnalytics
{
    public function __construct(private EntityManager $em, private AnalyticsCalendar $calendar) {}

    /** Personal metrics never inherit an all-production analytics grant. */
    public function get(User $user, ?DateTimeImmutable $now = null): array
    {
        $type = $user->get('smAccountType');
        $shiftMetrics = $user->isAdmin() || in_array($type, ['Operator', 'Model', 'ProducerAdmin'], true);
        $earnings = $user->isAdmin() || in_array($type,
            ['Operator', 'Model', 'ProducerAdmin', 'TechnicalDirector', 'Recruiter'], true);
        $week = $this->calendar->previousWeek($now);
        [$from, $to] = $this->calendar->range($week['from'], $week['to']);
        $range = ['endTime>=' => $from->format('Y-m-d H:i:s'), 'endTime<' => $to->format('Y-m-d H:i:s')];
        // A producer's shift metrics refer only to their own operator work.
        $where = ['deleted' => false, 'status' => SmShift::STATUS_CLOSED,
            $type === 'Model' ? 'modelId' : 'operatorId' => $user->getId()];
        $result = [
            'availableTabs' => array_merge($shiftMetrics ? ['average', 'hours', 'lastDate', 'lastCheck'] : [],
                $earnings ? ['earnings'] : []),
            'week' => $week, 'averageChecks' => [], 'workingSeconds' => 0,
            'hoursScope' => 'personal',
            'lastShift' => null, 'weeklyEarnings' => [],
        ];
        if ($shiftMetrics) {
            $query = $this->em->getQueryBuilder()->select()->from(SmShift::ENTITY_TYPE)
                ->select(['currency', ['AVG:result', 'amount'], ['COUNT:id', 'shiftCount']])
                ->where(array_merge($where, $range))->group(['currency'])->order('currency')->build();
            foreach ($this->em->getQueryExecutor()->execute($query)->fetchAll() as $row) {
                $result['averageChecks'][] = ['currency' => $row['currency'],
                    'amount' => number_format((float) $row['amount'], 2, '.', ''),
                    'shiftCount' => (int) $row['shiftCount']];
            }
            $query = $this->em->getQueryBuilder()->select()->from(SmShift::ENTITY_TYPE)
                ->select([[Expr::sum(Expr::greatest(
                    Expr::timestampDiffSecond(Expr::column('startTime'), Expr::column('endTime')),
                    Expr::value(0),
                )), 'seconds']])
                ->where(array_merge($where, $range))->build();
            $result['workingSeconds'] = (int) ($this->em->getQueryExecutor()->execute($query)->fetchColumn() ?: 0);
            $query = $this->em->getQueryBuilder()->select()->from(SmShift::ENTITY_TYPE)
                ->select(['endTime', 'result', 'currency'])->where($where)
                ->order('endTime', 'DESC')->order('id', 'DESC')->limit(0, 1)->build();
            $last = $this->em->getQueryExecutor()->execute($query)->fetch();
            if ($last) {
                $result['lastShift'] = ['endedAt' => $last['endTime'], 'currency' => $last['currency'],
                    'amount' => number_format((float) $last['result'], 2, '.', '')];
            }
        }
        if ($earnings) {
            $query = $this->em->getQueryBuilder()->select()->from(SmShiftExpense::ENTITY_TYPE)
                ->select(['currency', ['SUM:amount', 'amount']])
                ->where(['deleted' => false, 'userId' => $user->getId(),
                    'incurredAt>=' => $from->format('Y-m-d H:i:s'),
                    'incurredAt<' => $to->format('Y-m-d H:i:s')])
                ->group(['currency'])->order('currency')->build();
            foreach ($this->em->getQueryExecutor()->execute($query)->fetchAll() as $row) {
                $result['weeklyEarnings'][] = ['currency' => $row['currency'],
                    'amount' => number_format((float) $row['amount'], 2, '.', '')];
            }
        }
        return $result;
    }
}
