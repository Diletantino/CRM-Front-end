<?php

namespace Espo\Modules\StudioManagement\Api;

use Espo\Core\Acl;
use Espo\Modules\StudioManagement\Tools\RolePolicy;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Entities\User;
use Espo\Modules\StudioManagement\Tools\ModelShiftDefaults;
use Espo\ORM\EntityManager;

final class GetModelShiftDefaults implements Action
{
    public function __construct(
        private EntityManager $entityManager,
        private User $user,
        private Acl $acl,
        private RolePolicy $policy,
        private ModelShiftDefaults $defaults,
    ) {}

    public function process(Request $request): Response
    {
        $model = $this->entityManager->getEntityById('User', (string) $request->getRouteParam('id'));
        if (!$model || !$model->get('isActive') || $model->get('smAccountType') !== 'Model') {
            throw new NotFound();
        }
        $allowed = $this->policy->level($this->user, 'smShiftPrepare', 'own') === 'all' &&
            ($this->acl->checkScope('SmShift', 'create') || $this->acl->checkScope('SmShift', 'edit'));
        $shiftId = $request->getQueryParam('shiftId');
        if (!$allowed && $shiftId) {
            $shift = $this->entityManager->getEntityById('SmShift', $shiftId);
            $allowed = $shift && $shift->get('modelId') === $model->getId() &&
                $shift->get('status') === 'Draft' && $this->acl->checkEntityEdit($shift) &&
                $this->policy->canPrepare($this->user, $shift);
        } elseif (!$allowed && $this->acl->checkScope('SmShift', 'create')) {
            $teams = $this->entityManager->getRDBRepository('User')->getRelation($model, 'teams')->find();
            foreach ($teams as $team) {
                $draft = $this->entityManager->getNewEntity('SmShift');
                $draft->set(['operatorId' => $team->get('smOperatorId'),
                    'producerId' => $team->get('smProducerId')]);
                if ($this->acl->checkEntityRead($team) && $this->policy->canPrepare($this->user, $draft)) {
                    $allowed = true;
                    break;
                }
            }
        }
        if (!$allowed) {
            throw new Forbidden();
        }
        return ResponseComposer::json($this->defaults->get($model));
    }
}
