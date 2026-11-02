<?php
// Local CRM only. All fixtures are rolled back, including on assertion failure.
require '/var/www/html/bootstrap.php';

use Espo\Core\Application;
use Espo\Core\ApplicationUser;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Config;
use Espo\ORM\EntityManager;
use Espo\Modules\StudioManagement\Tools\AnalyticsCalendar;
use Espo\Modules\StudioManagement\Tools\AnalyticsService;
use Espo\Modules\StudioManagement\Tools\PersonalAnalytics;

$app = new Application();
$container = $app->getContainer();
$em = $container->getByClass(EntityManager::class);
$admin = $em->getRDBRepository('User')->where(['type' => 'admin', 'isActive' => true])->findOne();
$container->getByClass(ApplicationUser::class)->setUser($admin);
$config = clone $container->getByClass(Config::class);
$config->set('timeZone', 'Europe/Berlin');
$calendar = new AnalyticsCalendar($config);
$personal = new PersonalAnalytics($em, $calendar);
$checks = 0;
$eq = function ($actual, $expected, $message) use (&$checks) {
    if ($actual !== $expected) throw new RuntimeException($message . ': ' . json_encode([$actual, $expected]));
    $checks++;
};
$rollback = new RuntimeException('ROLLBACK_ANALYTICS_FIXTURES');
try {
    $em->getTransactionManager()->run(function () use ($em, $personal, $calendar, $eq, $rollback, $app) {
        $now = new DateTimeImmutable('2026-10-28T12:00:00Z');
        $eq($calendar->previousWeek($now)['from'], '2026-10-19', 'Previous Monday');
        $eq($calendar->previousWeek($now)['to'], '2026-10-25', 'Previous Sunday');
        [$from, $to] = $calendar->range('2026-10-19', '2026-10-25');
        $eq($from->format('Y-m-d H:i:s'), '2026-10-18 22:00:00', 'Local midnight converted to UTC');
        $eq($to->format('Y-m-d H:i:s'), '2026-10-25 23:00:00', 'DST calendar end boundary');
        $eq(($to->getTimestamp() - $from->getTimestamp()) / 3600, 169, 'DST week has 169 elapsed hours');
        foreach ([['2026-02-30', '2026-03-01'], ['2026-10-31', '2026-10-01'], ['2023-01-01', '2026-01-01']] as $dates) {
            try { $calendar->range(...$dates); $eq(true, false, 'Invalid range accepted'); }
            catch (BadRequest) { $eq(true, true, 'Invalid range rejected'); }
        }
        $suffix = bin2hex(random_bytes(5));
        $new = function ($type, $data) use ($em) {
            $entity = $em->getNewEntity($type); $entity->set($data);
            $em->saveEntity($entity, ['skipHooks' => true]); return $entity;
        };
        $users = [];
        foreach (['Operator', 'Model', 'ProducerAdmin', 'TechnicalDirector', 'Recruiter', 'Other'] as $type) {
            $users[$type] = $new('User', ['userName' => "analytics-$type-$suffix", 'name' => $type,
                'type' => 'regular', 'isActive' => true, 'smAccountType' => $type === 'Other' ? 'Operator' : $type]);
        }
        $shift = function ($operator, $producer, $start, $end, $amount, $status = 'Closed', $currency = 'USD') use ($new, $users, $suffix) {
            return $new('SmShift', ['name' => "Analytics $suffix", 'operatorId' => $users[$operator]->getId(),
                'producerId' => $users[$producer]->getId(), 'modelId' => $users['Model']->getId(),
                'startTime' => $start, 'endTime' => $end, 'result' => $amount, 'status' => $status, 'currency' => $currency]);
        };
        $a = $shift('Operator', 'ProducerAdmin', '2026-10-19 08:00:00', '2026-10-19 10:00:00', '100.00');
        $shift('ProducerAdmin', 'ProducerAdmin', '2026-10-20 08:00:00', '2026-10-20 11:30:00', '200.00');
        $shift('ProducerAdmin', 'ProducerAdmin', '2026-10-25 21:59:00', '2026-10-25 22:59:00', '400.00');
        $shift('ProducerAdmin', 'Other', '2026-10-22 12:00:00', '2026-10-22 13:00:00', '300.00');
        $shift('Other', 'Other', '2026-10-21 00:00:00', '2026-10-21 10:00:00', '999.00');
        $shift('ProducerAdmin', 'ProducerAdmin', '2026-10-21 00:00:00', '2026-10-21 10:00:00', '999.00', 'Review');
        $shift('ProducerAdmin', 'ProducerAdmin', '2026-10-25 22:30:00', '2026-10-25 23:00:00', '900.00');
        // Zero-valued shifts count in averages. Different currencies must not be mixed.
        $shift('Operator', 'Other', '2026-10-22 08:00:00', '2026-10-22 08:30:00', '0.00');
        $shift('Operator', 'Other', '2026-10-22 10:00:00', '2026-10-22 11:00:00', '50.00', 'Closed', 'EUR');
        foreach (['Operator' => '25.00', 'Model' => '40.00', 'ProducerAdmin' => '10.00',
            'TechnicalDirector' => '5.00', 'Recruiter' => '5.00', 'Other' => '99.00'] as $type => $amount) {
            $new('SmShiftExpense', ['name' => "Analytics $suffix", 'userId' => $users[$type]->getId(),
                'shiftId' => $a->getId(), 'category' => $type === 'ProducerAdmin' ? 'Producer' : ($type === 'Other' ? 'Operator' : $type),
                'amount' => $amount, 'currency' => 'USD', 'incurredAt' => '2026-10-19 12:00:00']);
        }
        $new('SmShiftExpense', ['name' => "Analytics $suffix", 'userId' => $users['ProducerAdmin']->getId(),
            'shiftId' => $a->getId(), 'category' => 'Operator', 'amount' => '25.00', 'currency' => 'USD',
            'incurredAt' => '2026-10-25 22:59:59']);
        $new('SmShiftExpense', ['name' => "Analytics $suffix", 'userId' => $users['ProducerAdmin']->getId(),
            'shiftId' => $a->getId(), 'category' => 'Operator', 'amount' => '999.00', 'currency' => 'USD',
            'incurredAt' => '2026-10-25 23:00:00']);
        $p = $personal->get($users['ProducerAdmin'], $now);
        $eq($p['averageChecks'][0]['amount'], '300.00', 'Producer checks only operator shifts');
        $eq($p['averageChecks'][0]['shiftCount'], 3, 'Pending and next-week shifts excluded');
        $eq($p['workingSeconds'], 19800, 'Producer hours include only their operator shifts');
        $eq($p['hoursScope'], 'personal', 'Producer hours scope');
        $eq($p['lastShift']['amount'], '900.00', 'Latest check is all-time operator shift');
        $eq($p['lastShift']['endedAt'], '2026-10-25 23:00:00', 'Date and amount from same shift');
        $eq($p['weeklyEarnings'][0]['amount'], '35.00', 'Dual-role personal payouts summed, other users excluded');
        $op = $personal->get($users['Operator'], $now);
        $eq($op['workingSeconds'], 12600, 'Own operator hours only');
        $eq($op['averageChecks'][0]['currency'], 'EUR', 'Currencies separated');
        $eq($op['averageChecks'][0]['amount'], '50.00', 'EUR average');
        $eq($op['averageChecks'][1]['amount'], '50.00', 'Zero check counts in USD average');
        $eq($op['weeklyEarnings'][0]['amount'], '25.00', 'Operator payout is not shift gross');
        foreach (['Operator', 'Model', 'ProducerAdmin'] as $type) {
            $eq(count($personal->get($users[$type], $now)['availableTabs']), 5, "$type metric visibility");
        }
        foreach (['TechnicalDirector', 'Recruiter'] as $type) {
            $m = $personal->get($users[$type], $now);
            $eq($m['availableTabs'], ['earnings'], "$type only earnings");
            $eq($m['lastShift'], null, "$type receives no shift details");
            $eq($m['weeklyEarnings'][0]['amount'], '5.00', "$type personal payout");
        }
        $empty = $new('User', ['userName' => "analytics-empty-$suffix", 'type' => 'regular', 'smAccountType' => 'Model']);
        $m = $personal->get($empty, $now);
        $eq($m['lastShift'], null, 'No fabricated latest shift');
        $eq($m['workingSeconds'], 0, 'Empty hours');
        $eq($m['averageChecks'], [], 'Empty average');
        $eq($m['weeklyEarnings'], [], 'Empty earnings');
        throw $rollback;
    });
} catch (RuntimeException $error) {
    if ($error !== $rollback) throw $error;
}
echo "Analytics integration: $checks checks passed; all fixtures rolled back.\n";
