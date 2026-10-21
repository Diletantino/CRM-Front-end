<?php
// Soft-deletes only exact IDs recorded by the paired disposable-localhost test.
require '/var/www/html/bootstrap.php';
use Espo\Core\Application;
use Espo\Core\Utils\Config;
use Espo\ORM\EntityManager;
$app = new Application();
$container = $app->getContainer();
if (!in_array(parse_url((string) $container->getByClass(Config::class)->get('siteUrl'), PHP_URL_HOST),
    ['localhost', '127.0.0.1'], true)) {
    throw new RuntimeException('Local disposable CRM only.');
}
$registry = json_decode(file_get_contents('/tmp/dynamic-roles-fixtures.json'), true, flags: JSON_THROW_ON_ERROR);
$prefix = $registry['prefix'] ?? '';
if (!preg_match('/^DynamicRoles [a-f0-9]{10}$/', $prefix)) throw new RuntimeException('Invalid fixture prefix.');
$em = $container->getByClass(EntityManager::class);
// A failed HTTP response can occur after persistence. Recover only this run's unique role prefix.
foreach ($em->getRDBRepository('Role')->where(['name*' => $prefix . ' %'])->find() as $role) {
    if (str_starts_with((string) $role->get('name'), $prefix . ' ') &&
        !in_array(['Role', $role->getId()], $registry['fixtures'], true)) {
        $registry['fixtures'][] = ['Role', $role->getId()];
    }
}
$ids = [];
foreach ($registry['fixtures'] as [$type, $id]) $ids[$type][] = $id;
$shiftIds = $ids['SmShift'] ?? [];
$count = 0;
$em->getTransactionManager()->run(function () use ($em, $registry, $prefix, $shiftIds, &$count) {
    foreach (['SmShiftExpense', 'SmShiftFinancialSnapshot', 'Note'] as $type) {
        $where = $type === 'Note' ? ['parentId' => $shiftIds, 'parentType' => 'SmShift'] : ['shiftId' => $shiftIds];
        foreach ($em->getRDBRepository($type)->where($where)->find() as $entity) {
            $em->removeEntity($entity, ['skipHooks' => true]); $count++;
        }
    }
    foreach (array_reverse($registry['fixtures']) as [$type, $id]) {
        if (!in_array($type, ['Role', 'User', 'Team', 'SmShift', 'SmRegistrationLink'], true)) throw new RuntimeException('Invalid fixture type.');
        $entity = $em->getEntityById($type, $id);
        if (!$entity) continue;
        if ($type === 'SmRegistrationLink') {
            $role = $em->getEntityById('Role', (string) $entity->get('targetRoleId'));
            // Roles can have been deleted earlier in reverse order. Check the recorded exact role IDs too.
            $roleIds = array_column(array_filter($registry['fixtures'], fn ($f) => $f[0] === 'Role'), 1);
            if (!in_array($entity->get('targetRoleId'), $roleIds, true)) throw new RuntimeException('Unowned link.');
        } elseif ($type === 'SmShift') {
            $teamIds = array_column(array_filter($registry['fixtures'], fn ($f) => $f[0] === 'Team'), 1);
            $team = $em->getEntityById('Team', (string) $entity->get('teamId'));
            if (!$team || !in_array($team->getId(), $teamIds, true) ||
                !str_starts_with((string) $team->get('name'), $prefix . ' ')) {
                throw new RuntimeException('Unowned shift fixture.');
            }
        } elseif (!str_starts_with((string) $entity->get('name'), $prefix) &&
            !($type === 'User' && str_starts_with((string) $entity->get('userName'), 'dynrole-'))) {
            throw new RuntimeException('Unowned fixture: ' . $type);
        }
        $em->removeEntity($entity, ['skipHooks' => true]); $count++;
    }
});
echo "Dynamic-role fixtures soft-deleted: $count\n";
