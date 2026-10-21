<?php

namespace Espo\Modules\StudioManagement\Api;

use Espo\Core\Acl;
use Espo\Modules\StudioManagement\Tools\RolePolicy;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\Forbidden;
use Espo\Entities\User;
use Espo\Modules\StudioManagement\Tools\TeamComposition;
use Espo\Modules\StudioManagement\Tools\ModelShiftDefaults;
use Espo\ORM\EntityManager;
use Espo\Modules\StudioManagement\Tools\WorkflowError;

final class GetTeamShiftDefaults implements Action
{
    public function __construct(private EntityManager $em, private User $user, private Acl $acl,
        private TeamComposition $composition, private ModelShiftDefaults $defaults, private RolePolicy $policy) {}

    public function process(Request $request): Response
    {
        $team = $this->em->getEntityById('Team', (string) $request->getRouteParam('id'));
        if (!$team) {
            throw WorkflowError::notFound('Группа не найдена.');
        }
        if (!$this->acl->checkScope('SmShift', 'create')) {
            throw new Forbidden();
        }
        $members = $this->composition->resolve($team, true);
        $draft = $this->em->getNewEntity('SmShift');
        $draft->set(['operatorId' => $members['smOperator']->getId(),
            'producerId' => $members['smProducer']->getId()]);
        if (!$this->acl->checkEntityRead($team) || !$this->policy->canPrepare($this->user, $draft)) {
            throw new Forbidden();
        }
        $values = $this->defaults->get($members['smModel']);
        foreach (['smProducer' => 'producer', 'smOperator' => 'operator', 'smModel' => 'model'] as $slot => $field) {
            $values[$field . 'Id'] = $members[$slot]->getId();
            $values[$field . 'Name'] = $members[$slot]->get('name');
        }
        return ResponseComposer::json($values);
    }
}
