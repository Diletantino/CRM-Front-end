<?php

namespace Espo\Modules\StudioManagement\Tools;

use Espo\ORM\EntityManager;

final class StaffMigration
{
    public function __construct(private AccountTypeResolver $resolver, private EntityManager $entityManager) {}

    /** @return array{users: int, shifts: int} */
    public function run(): array
    {
        $users = $this->resolver->syncExisting();
        $count = 0;
        $shifts = $this->entityManager->getRDBRepository('SmShift')
            ->where(['assignedUserId' => null, 'operatorId!=' => null])->find();
        foreach ($shifts as $shift) {
            $shift->set('assignedUserId', $shift->get('operatorId'));
            $this->entityManager->saveEntity($shift, ['skipBeforeSave' => true]);
            $count++;
        }
        return ['users' => $users, 'shifts' => $count];
    }
}
