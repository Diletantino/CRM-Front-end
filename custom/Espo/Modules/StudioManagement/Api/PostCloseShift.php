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

        if (
            !$this->user->isAdmin() &&
            (
                $this->user->get('smAccountType') !== 'Operator' ||
                $shift->get('operatorId') !== $this->user->getId()
            )
        ) {
            throw new Forbidden('Only the assigned operator can complete this shift.');
        }

        $siteData = $data->siteData ?? null;

        if (!is_array($siteData)) {
            throw new BadRequest('Site calculation rows are required.');
        }

        $screenshotIds = $data->screenshotsIds ?? [];

        if (!is_array($screenshotIds)) {
            throw new BadRequest('Screenshot IDs must be an array.');
        }

        $shift = $this->service->close($id, $siteData, $screenshotIds, $this->user);

        return ResponseComposer::json($shift->getValueMap());
    }
}
