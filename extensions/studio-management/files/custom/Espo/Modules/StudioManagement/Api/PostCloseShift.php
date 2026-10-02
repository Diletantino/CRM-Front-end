<?php

namespace Espo\Modules\StudioManagement\Api;

use Espo\Core\Acl;
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
use stdClass;

final class PostCloseShift implements Action
{
    public function __construct(
        private Acl $acl,
        private ShiftService $service,
        private User $user,
        private EntityManager $entityManager,
    ) {}

    public function process(Request $request): Response
    {
        $id = $request->getRouteParam('id');
        $data = $request->getParsedBody();

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

        $gross = $data->totalGross ?? null;

        if (!is_string($gross) && !is_int($gross) && !is_float($gross)) {
            throw new BadRequest('A gross income value is required.');
        }

        $metrics = $data->platformMetrics ?? new stdClass();

        if (!$metrics instanceof stdClass) {
            throw new BadRequest('Platform metrics must be a JSON object.');
        }

        $shift = $this->service->close($id, $gross, $metrics, $this->user);

        return ResponseComposer::json($shift->getValueMap());
    }
}
