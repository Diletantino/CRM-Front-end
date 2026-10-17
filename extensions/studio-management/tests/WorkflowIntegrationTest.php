<?php

// Run in the installed CRM: php /tmp/WorkflowIntegrationTest.php
// All fixtures and financial snapshots are rolled back, including on failure.
require '/var/www/html/bootstrap.php';

use Espo\Core\Application;
use Espo\Core\ApplicationUser;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Acl;
use Espo\Core\Api\RequestWrapper;
use Espo\Modules\StudioManagement\Api\GetModelShiftDefaults;
use GuzzleHttp\Psr7\ServerRequest;
use Espo\Modules\StudioManagement\Tools\AccountTypeResolver;
use Espo\Modules\StudioManagement\Tools\ModelShiftDefaults;
use Espo\Modules\StudioManagement\Tools\ShiftService;
use Espo\Modules\StudioManagement\Tools\StaffService;
use Espo\Modules\StudioManagement\Tools\StaffMigration;
use Espo\ORM\EntityManager;

$app = new Application();
$container = $app->getContainer();
$em = $container->getByClass(EntityManager::class);
$admin = $em->getRDBRepository('User')->where(['type' => 'admin', 'isActive' => true])->findOne();
$container->getByClass(ApplicationUser::class)->setUser($admin);
$factory = $app->getInjectableFactory();
$checks = 0;
$assert = function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
};
$rollback = new RuntimeException('ROLLBACK_TEST_FIXTURES');

try {
    $em->getTransactionManager()->run(function () use ($em, $factory, $admin, $assert, $rollback): void {
        $suffix = bin2hex(random_bytes(5));
        $createUser = function (string $type, array $extra = []) use ($em, $suffix) {
            $user = $em->getNewEntity('User');
            $user->set(array_merge([
                'userName' => 'regression-' . strtolower($type) . '-' . $suffix,
                'firstName' => 'Regression', 'lastName' => $type,
                'type' => 'regular', 'isActive' => true, 'smAccountType' => $type,
            ], $extra));
            $em->saveEntity($user);
            return $user;
        };
        $role = $em->getNewEntity('Role');
        $role->set(['name' => 'Модель', 'smSharePercent' => 0]);
        $em->saveEntity($role);
        $model = $createUser('Model', [
            'smAccountType' => '', 'rolesIds' => [$role->getId()],
            'smBirthDate' => '2001-01-01',
            'smSiteData' => [['site' => 'CB', 'login' => 'regression', 'password' => 'fixture-only']],
        ]);
        $assert($model->get('smAccountType') === 'Model', 'Manual role-based user creation must assign studio type.');

        $legacy = $createUser('Legacy', ['smAccountType' => '', 'title' => 'Модель']);
        $legacy->set('smAccountType', '');
        $em->saveEntity($legacy, ['skipBeforeSave' => true]);
        $resolver = $factory->create(AccountTypeResolver::class);
        $resolver->syncExisting();
        $legacy = $em->getEntityById('User', $legacy->getId());
        $assert($legacy->get('smAccountType') === 'Model', 'Existing manually created model must be repaired.');

        $producer = $createUser('ProducerAdmin');
        $operator = $createUser('Operator');
        $recruiter = $createUser('Recruiter');
        $team = $em->getNewEntity('Team');
        $team->set(['name' => 'Regression ' . $suffix, 'smProducerId' => $producer->getId()]);
        $em->saveEntity($team);
        foreach ([$model, $operator] as $member) {
            $em->getRDBRepository('Team')->getRelation($team, 'users')->relateById($member->getId());
        }
        $rows = $factory->create(StaffService::class)->getList('model', $admin)['rows'];
        $assert(in_array($model->getId(), array_column($rows, 'id'), true), 'Model must appear in the staff list.');
        $assert(in_array($legacy->getId(), array_column($rows, 'id'), true), 'Legacy model must appear in the staff list.');
        $producerRows = $factory->create(StaffService::class)->getList('model', $producer)['rows'];
        $assert(in_array($model->getId(), array_column($producerRows, 'id'), true), 'Producer sees their models.');
        $assert(!in_array($legacy->getId(), array_column($producerRows, 'id'), true), 'Producer cannot see another team.');

        $request = new RequestWrapper(new ServerRequest('GET', '/'), '', ['id' => $model->getId()]);
        $response = $factory->create(GetModelShiftDefaults::class)->process($request);
        $defaults = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $assert($defaults['siteData'][0]['login'] === 'regression', 'Defaults API returns model access to an authorized viewer.');
        $outsider = $em->getNewEntity('User');
        $outsider->set(['id' => 'regression-outsider', 'smAccountType' => 'Operator', 'type' => 'regular']);
        $api = new GetModelShiftDefaults($em, $outsider, $factory->create(Acl::class), $factory->create(ModelShiftDefaults::class));
        try {
            $api->process($request);
            $assert(false, 'An unrelated operator must not receive model credentials.');
        } catch (Forbidden) {
            $assert(true, 'Unrelated operator cannot load model defaults.');
        }

        $shift = $em->getNewEntity('SmShift');
        $shift->set([
            'name' => 'Regression ' . $suffix, 'teamId' => $team->getId(),
            'modelId' => $model->getId(), 'operatorId' => $operator->getId(),
            'startTime' => '2026-01-01 14:00:00', 'currency' => 'USD',
        ]);
        $em->saveEntity($shift);
        $assert($shift->get('status') === 'Draft', 'Created shift must be preliminary.');
        $assert($shift->get('assignedUserId') === $operator->getId(), 'Operator owns the shift for ACL.');
        $shift->set('assignedUserId', null);
        $em->saveEntity($shift, ['skipBeforeSave' => true]);
        $factory->create(StaffMigration::class)->run();
        $shift = $em->getEntityById('SmShift', $shift->getId());
        $assert($shift->get('assignedUserId') === $operator->getId(), 'Existing shifts must regain assigned-operator access.');
        $assert($shift->get('startTime') === '2026-01-01 14:00:00', 'Planned start must be saved.');
        $assert(((array) $shift->get('siteData')[0])['login'] === 'regression', 'Profile credentials copied into draft.');
        $assert($shift->get('modelBirthDate') === '2001-01-01', 'Birth date copied from profile.');

        $service = $factory->create(ShiftService::class);
        $shift = $service->open($shift->getId());
        $assert($shift->get('status') === 'Open', 'Start transitions Draft to Open.');
        $assert($shift->get('startTime') !== '2026-01-01 14:00:00', 'Actual start replaces planned start.');
        $assert(substr($shift->get('startTime'), -2) === '00', 'Start uses minute precision.');
        try {
            $service->open($shift->getId());
            $assert(false, 'Starting twice must be rejected.');
        } catch (Conflict) {
            $assert(true, 'Duplicate start rejected.');
        }
        $sites = array_map(fn ($row) => (array) $row, $shift->get('siteData'));
        $sites[0]['state'] = 'Started';
        $shift->set('siteData', $sites);
        $em->saveEntity($shift);
        $assert(((array) $shift->get('siteData')[0])['state'] === 'Started', 'Active site can be started.');

        $shift = $service->finish($shift->getId());
        $assert($shift->get('status') === 'Counting', 'Finish transitions Open to Counting.');
        $sites[0]['earning'] = '125.00';
        $sites[0]['offlineBonus'] = '10.00';
        $shift = $service->close($shift->getId(), $sites, [], $admin);
        $assert($shift->get('status') === 'Closed', 'Count transitions Counting to Closed.');
        $assert((float) $shift->get('totalGross') === 135.0, 'Online and offline income summed correctly.');
        $assert((bool) $em->getRDBRepository('SmShiftFinancialSnapshot')->where(['shiftId' => $shift->getId()])->findOne(), 'Close creates financial snapshot.');
        try {
            $service->close($shift->getId(), $sites, [], $admin);
            $assert(false, 'Closing twice must be rejected.');
        } catch (Conflict) {
            $assert(true, 'Duplicate close rejected.');
        }

        $invite = $em->getNewEntity('SmRegistrationLink');
        $invite->set(['accountType' => 'Model', 'targetRoleId' => $role->getId(), 'recruiterId' => $recruiter->getId()]);
        $em->saveEntity($invite);
        $assert(strlen($invite->get('token')) === 64, 'Valid recruiter can create invitation.');
        $badInvite = $em->getNewEntity('SmRegistrationLink');
        $badInvite->set(['accountType' => 'Model', 'targetRoleId' => $role->getId(), 'recruiterId' => $operator->getId()]);
        try {
            $em->saveEntity($badInvite);
            $assert(false, 'Non-recruiter must be rejected.');
        } catch (BadRequest $e) {
            $assert(str_contains($e->getMessage(), 'Рекрутер'), 'Recruiter validation explains the problem in Russian.');
        }

        $model->set(['smSiteData' => [], 'smSiteAccess' => 'CB - legacy-login - legacy-pass']);
        $defaults = $factory->create(ModelShiftDefaults::class)->get($model);
        $assert($defaults['siteData'][0]['login'] === 'legacy-login', 'Legacy profile access text remains supported.');
        throw $rollback;
    });
} catch (Throwable $e) {
    if ($e !== $rollback) {
        fwrite(STDERR, $e . PHP_EOL);
        exit(1);
    }
}
echo "Workflow integration: $checks checks passed; all fixtures rolled back.\n";
