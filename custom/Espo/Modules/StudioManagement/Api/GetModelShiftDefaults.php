<?php

namespace Espo\Modules\StudioManagement\Api;

use Espo\Core\Acl;
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
        private ModelShiftDefaults $defaults,
    ) {}

    public function process(Request $request): Response
    {
        $model = $this->entityManager->getEntityById('User', (string) $request->getRouteParam('id'));
        if (!$model || !$model->get('isActive') || $model->get('smAccountType') !== 'Model') {
            throw new NotFound();
        }
        $allowed = $this->user->isAdmin();
        $shiftId = $request->getQueryParam('shiftId');
        if (!$allowed && $shiftId) {
            $shift = $this->entityManager->getEntityById('SmShift', $shiftId);
            $team = $shift && $shift->get('teamId')
                ? $this->entityManager->getEntityById('Team', $shift->get('teamId')) : null;
            $allowed = $shift && $shift->get('modelId') === $model->getId() &&
                $shift->get('status') === 'Draft' && $this->acl->checkEntityEdit($shift) &&
                ($shift->get('operatorId') === $this->user->getId() ||
                    ($this->user->get('smAccountType') === 'ProducerAdmin' &&
                        $team && $team->get('smProducerId') === $this->user->getId()));
        } elseif (!$allowed && $this->acl->checkScope('SmShift', 'create')) {
            $teams = $this->entityManager->getRDBRepository('User')->getRelation($model, 'teams')->find();
            foreach ($teams as $team) {
                if ($team->get('smProducerId') === $this->user->getId()) {
                    $allowed = true;
                    break;
                }
                if ($this->user->get('smAccountType') === 'Operator' &&
                    $this->entityManager->getRDBRepository('Team')->getRelation($team, 'users')
                        ->where(['id' => $this->user->getId()])->findOne()) {
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
