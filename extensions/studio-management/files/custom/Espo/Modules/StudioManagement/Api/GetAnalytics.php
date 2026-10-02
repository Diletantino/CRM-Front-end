<?php

namespace Espo\Modules\StudioManagement\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\Forbidden;
use Espo\Entities\User;
use Espo\Modules\StudioManagement\Entities\SmShiftFinancialSnapshot;
use Espo\Modules\StudioManagement\Tools\AnalyticsService;

final class GetAnalytics implements Action
{
    public function __construct(
        private Acl $acl,
        private AnalyticsService $service,
        private User $user,
    ) {}

    public function process(Request $request): Response
    {
        if (
            !$this->acl->checkScope('SmAnalytics') ||
            !$this->acl->check(SmShiftFinancialSnapshot::ENTITY_TYPE, Acl\Table::ACTION_READ)
        ) {
            throw new Forbidden();
        }

        $from = (string) ($request->getQueryParam('from') ?? '');
        $to = (string) ($request->getQueryParam('to') ?? '');

        return ResponseComposer::json($this->service->get($from, $to, $this->user));
    }
}
