<?php

// Disposable local-browser fixture. Run `create`, then `cleanup` after UI tests.
require '/var/www/html/bootstrap.php';

use Espo\Core\Application;
use Espo\Core\ApplicationUser;
use Espo\ORM\EntityManager;

$app = new Application();
$em = $app->getContainer()->getByClass(EntityManager::class);
$admin = $em->getRDBRepository('User')->where(['type' => 'admin', 'isActive' => true])->findOne();
$app->getContainer()->getByClass(ApplicationUser::class)->setUser($admin);
$registry = '/var/www/html/data/studio-ui-fixture.json';

if (($argv[1] ?? '') === 'cleanup') {
    $ids = json_decode(is_file($registry) ? file_get_contents($registry) : ($argv[2] ?? ''), true, flags: JSON_THROW_ON_ERROR);
    if (array_keys($ids) !== ['User', 'Team', 'SmShift'] || count($ids['User']) !== 3) {
        throw new RuntimeException('Unexpected fixture registry.');
    }
    $shift = $em->getEntityById('SmShift', $ids['SmShift']);
    if (!$shift || $shift->get('teamId') !== $ids['Team'] ||
        !in_array($shift->get('modelId'), $ids['User'], true) ||
        !in_array($shift->get('operatorId'), $ids['User'], true)) {
        throw new RuntimeException('Fixture ownership does not match.');
    }
    foreach ($ids['User'] as $id) {
        $user = $em->getEntityById('User', $id);
        if (!$user || !str_starts_with((string) $user->get('userName'), 'ui-')) {
            throw new RuntimeException('Refusing to clean a non-test user.');
        }
    }
    $em->getTransactionManager()->run(function () use ($em, $ids): void {
        $query = $em->getQueryBuilder()->update()->in('Note')->set(['deleted' => true])
            ->where(['parentId' => $ids['SmShift'], 'parentType' => 'SmShift'])->build();
        $em->getQueryExecutor()->execute($query);
        foreach (['SmShiftExpense', 'SmShiftFinancialSnapshot'] as $type) {
            $query = $em->getQueryBuilder()->update()->in($type)
                ->set(['deleted' => true])->where(['shiftId' => $ids['SmShift']])->build();
            $em->getQueryExecutor()->execute($query);
        }
        foreach ($ids as $type => $id) {
            $query = $em->getQueryBuilder()->update()->in($type)
                ->set(['deleted' => true])->where(['id' => $id])->build();
            $em->getQueryExecutor()->execute($query);
        }
    });
    if (is_file($registry)) {
        unlink($registry);
    }
    echo "Disposable UI fixtures soft-deleted.\n";
    exit;
}

if (is_file($registry)) {
    $old = json_decode(file_get_contents($registry), true);
    if ($em->getEntityById('SmShift', $old['SmShift'] ?? '')) {
        throw new RuntimeException('Clean up the previous fixture first.');
    }
}
$ids = $em->getTransactionManager()->run(function () use ($em): array {
    $suffix = bin2hex(random_bytes(5));
    $users = [];
    foreach (['Model', 'Operator', 'ProducerAdmin'] as $type) {
        $user = $em->getNewEntity('User');
        $user->set([
            'userName' => 'ui-' . strtolower($type) . '-' . $suffix,
            'firstName' => 'UI Test', 'lastName' => $type, 'type' => 'regular',
            'isActive' => true, 'smAccountType' => $type,
        ]);
        if ($type === 'Model') {
            $user->set([
                'smBirthDate' => '2001-01-01',
                'smSiteData' => [['site' => 'CB', 'login' => 'demo-login', 'password' => 'demo-pass']],
            ]);
        }
        $em->saveEntity($user);
        $users[$type] = $user;
    }
    $team = $em->getNewEntity('Team');
    $team->set(['name' => 'UI Test Team ' . $suffix, 'smProducerId' => $users['ProducerAdmin']->getId()]);
    $em->saveEntity($team);
    foreach ($users as $member) {
        $em->getRDBRepository('Team')->getRelation($team, 'users')->relateById($member->getId());
    }
    $shift = $em->getNewEntity('SmShift');
    $shift->set([
        'name' => 'UI Test Shift ' . $suffix, 'teamId' => $team->getId(),
        'operatorId' => $users['Operator']->getId(), 'modelId' => $users['Model']->getId(),
        'startTime' => '2026-01-01 14:00:00', 'currency' => 'USD',
    ]);
    $em->saveEntity($shift);
    return ['User' => array_map(fn ($user) => $user->getId(), array_values($users)),
        'Team' => $team->getId(), 'SmShift' => $shift->getId()];
});
file_put_contents($registry, json_encode($ids, JSON_THROW_ON_ERROR));
echo json_encode($ids, JSON_THROW_ON_ERROR) . PHP_EOL;
