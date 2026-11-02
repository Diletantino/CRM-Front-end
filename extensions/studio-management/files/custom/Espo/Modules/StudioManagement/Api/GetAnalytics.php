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
use Espo\Modules\StudioManagement\Tools\PersonalAnalytics;
use Espo\Modules\StudioManagement\Tools\RolePolicy;

final class GetAnalytics implements Action
{
    public function __construct(
        private Acl $acl,
        private AnalyticsService $service,
        private User $user,
        private PersonalAnalytics $personal,
        private RolePolicy $policy,
    ) {}

    public function process(Request $request): Response
    {
        $personalAccount = in_array($this->user->get('smAccountType'), ['Operator', 'Model'], true);
        $canReadStudio = $this->acl->checkScope('SmAnalytics') &&
            $this->acl->check(SmShiftFinancialSnapshot::ENTITY_TYPE, Acl\Table::ACTION_READ);
        // Personal access never grants snapshot, user, team or studio-finance access.
        // An explicit all-studio grant continues to support delegated director roles.
        if (!$this->user->isAdmin() && $personalAccount &&
            (!$canReadStudio || $this->policy->level($this->user, 'smAnalyticsAccess') !== 'all')) {
            return ResponseComposer::json(['personalOnly' => true,
                'personalMetrics' => $this->personal->get($this->user)]);
        }
        if (
            !$canReadStudio
        ) {
            throw new Forbidden();
        }

        $from = (string) ($request->getQueryParam('from') ?? '');
        $to = (string) ($request->getQueryParam('to') ?? '');

        return ResponseComposer::json($this->service->get($from, $to, $this->user));
    }
}
