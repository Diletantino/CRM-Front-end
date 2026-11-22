<?php

namespace Espo\Modules\StudioManagement\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\Forbidden;
use Espo\Entities\User;
use Espo\Modules\StudioManagement\Tools\PersonalAnalytics;
use Espo\Modules\StudioManagement\Tools\RolePolicy;

final class GetPersonalAnalytics implements Action
{
    public function __construct(private User $user, private RolePolicy $policy, private PersonalAnalytics $personal) {}

    public function process(Request $request): Response
    {
        if (!$this->policy->canReadPersonalAnalytics($this->user)) throw new Forbidden();
        return ResponseComposer::json([
            'personalOnly' => true, 'personalMetrics' => $this->personal->get($this->user),
        ])->setHeader('Cache-Control', 'no-store');
    }
}
