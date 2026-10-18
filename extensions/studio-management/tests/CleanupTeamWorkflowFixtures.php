<?php

// LOCAL disposable install only. Soft-deletes exclusively TeamWorkflowHttpTest fixtures.
require '/var/www/html/bootstrap.php';

use Espo\Core\Application;
use Espo\Core\Utils\Config;
use Espo\ORM\EntityManager;

$app = new Application();
if (!in_array(parse_url((string) $app->getContainer()->getByClass(Config::class)->get('siteUrl'), PHP_URL_HOST),
    ['localhost', '127.0.0.1'], true)) {
    throw new RuntimeException('Test cleanup is restricted to a disposable localhost install.');
}
$em = $app->getContainer()->getByClass(EntityManager::class);
$query = $em->getQueryBuilder()->select()->from('User')->withDeleted()->build();
$ids = [];
foreach ($em->getRDBRepository('User')->clone($query)->find() as $user) {
    if (preg_match('/^team-test-.+-[a-f0-9]{8}(?:@example\.invalid)?$/', (string) $user->get('userName'))) {
        $ids[] = $user->getId();
    }
}
$teamIds = [];
$teamQuery = $em->getQueryBuilder()->select()->from('Team')->withDeleted()->build();
foreach ($em->getRDBRepository('Team')->clone($teamQuery)->find() as $team) {
    if (preg_match('/^Тест — .+ [a-f0-9]{8}$/u', (string) $team->get('name')) ||
        ($team->getId() === ($argv[1] ?? null) && $team->get('name') === 'UI проверка группы')) {
        $teamIds[] = $team->getId();
    }
}
$counts = [];
$roleIds = [];
$roleQuery = $em->getQueryBuilder()->select()->from('Role')->withDeleted()->build();
foreach ($em->getRDBRepository('Role')->clone($roleQuery)->find() as $role) {
    if (preg_match('/^(?:Прод|Тест оператор) [a-f0-9]{8}$/u', (string) $role->get('name'))) {
        $roleIds[] = $role->getId();
    }
}
$remove = function (string $type, array $where) use ($em, &$counts): void {
    $count = 0;
    foreach ($em->getRDBRepository($type)->where($where)->find() as $entity) {
        $em->removeEntity($entity, ['skipHooks' => true]);
        $count++;
    }
    $counts[$type] = ($counts[$type] ?? 0) + $count;
};
$em->getTransactionManager()->run(function () use ($em, $ids, $teamIds, $roleIds, $remove): void {
    $shiftIds = [];
    foreach ($em->getRDBRepository('SmShift')->where(['teamId' => $teamIds])->find() as $shift) {
        $shiftIds[] = $shift->getId();
    }
    $remove('SmShiftExpense', ['teamId' => $teamIds]);
    $remove('SmShiftFinancialSnapshot', ['teamId' => $teamIds]);
    $remove('Note', ['parentId' => $shiftIds, 'parentType' => 'SmShift']);
    $remove('SmShift', ['id' => $shiftIds]);
    $remove('SmRegistrationLink', ['targetRoleId' => $roleIds]);
    $remove('User', ['id' => $ids]);
    $remove('Team', ['id' => $teamIds]);
    $remove('Role', ['id' => $roleIds]);
});
echo json_encode($counts, JSON_THROW_ON_ERROR) . PHP_EOL;
