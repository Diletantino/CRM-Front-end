<?php

namespace Espo\Modules\StudioManagement\Tools;

use Espo\ORM\EntityManager;

final class StaffMigration
{
    public function __construct(private AccountTypeResolver $resolver, private EntityManager $entityManager,
        private TeamComposition $composition) {}

    /** @return array{users: int, shifts: int, teams: int, incompleteTeams: int} */
    public function run(): array
    {
        $users = $this->resolver->syncExisting();
        $teams = 0;
        $incomplete = 0;
        foreach ($this->entityManager->getRDBRepository('Team')->find() as $team) {
            try {
                $this->composition->prepare($team);
                $this->entityManager->saveEntity($team);
                $teams++;
                $this->composition->resolve($team, true);
            } catch (\Espo\Core\Exceptions\BadRequest) {
                // Ambiguous or inactive legacy membership needs explicit human assignment.
                $incomplete++;
            }
        }
        $count = 0;
        $shifts = $this->entityManager->getRDBRepository('SmShift')
            ->where(['assignedUserId' => null, 'operatorId!=' => null])->find();
        foreach ($shifts as $shift) {
            $shift->set('assignedUserId', $shift->get('operatorId'));
            $this->entityManager->saveEntity($shift, ['skipBeforeSave' => true]);
            $count++;
        }
        return ['users' => $users, 'shifts' => $count, 'teams' => $teams, 'incompleteTeams' => $incomplete];
    }
}
