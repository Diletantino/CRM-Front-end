<?php

namespace Espo\Modules\StudioManagement\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Modules\StudioManagement\Tools\AccessPolicy;

final class GetAccessPolicy implements Action
{
    public function __construct(private AccessPolicy $accessPolicy) {}

    public function process(Request $request): Response
    {
        return ResponseComposer::json([
            'adminLoginAllowed' => $this->accessPolicy->isAdminRequestAllowed($request),
        ]);
    }
}
