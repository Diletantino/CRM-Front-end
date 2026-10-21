<?php

namespace Espo\Modules\StudioManagement\Api;

use Espo\Core\Acl;
use Espo\Modules\StudioManagement\Tools\RolePolicy;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Entities\User;
use Espo\Modules\StudioManagement\Entities\SmShift;
use Espo\Modules\StudioManagement\Tools\ShiftService;
use Espo\ORM\EntityManager;

final class PostOpenShift implements Action
{
    public function __construct(
        private Acl $acl,
        private RolePolicy $policy,
        private ShiftService $service,
        private EntityManager $entityManager,
        private User $user,
    ) {}

    public function process(Request $request): Response
    {
        $id = $request->getRouteParam('id');

        if (!is_string($id) || $id === '') {
            throw new BadRequest('No shift ID.');
        }

        $shift = $this->entityManager->getEntityById(SmShift::ENTITY_TYPE, $id);

        if (!$shift) {
            throw new NotFound('Shift not found.');
        }

        if (!$this->acl->checkEntityEdit($shift)) {
            throw new Forbidden();
        }

        if (!$this->policy->canOperate($this->user, $shift)) {
            throw new Forbidden('Your role cannot operate this shift.');
        }

        return ResponseComposer::json($this->service->open($id)->getValueMap());
    }
}
