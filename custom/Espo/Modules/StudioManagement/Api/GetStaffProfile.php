<?php

namespace Espo\Modules\StudioManagement\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Entities\User;
use Espo\Modules\StudioManagement\Tools\StaffService;

final class GetStaffProfile implements Action
{
    public function __construct(
        private Acl $acl,
        private StaffService $service,
        private User $user,
    ) {}

    public function process(Request $request): Response
    {
        $type = $request->getRouteParam('type');
        $id = $request->getRouteParam('id');

        if (!is_string($type) || !is_string($id) || $id === '') {
            throw new BadRequest('Staff type and ID are required.');
        }

        $scope = match ($type) {
            'model' => 'SmModels',
            'operator' => 'SmOperators',
            default => throw new BadRequest('Unknown staff type.'),
        };

        if (
            !$this->acl->checkScope($scope) ||
            !$this->acl->check(User::ENTITY_TYPE, Acl\Table::ACTION_READ)
        ) {
            throw new Forbidden();
        }

        return ResponseComposer::json($this->service->getProfile($type, $id, $this->user));
    }
}
