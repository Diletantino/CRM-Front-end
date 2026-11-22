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
use Espo\Modules\StudioManagement\Tools\AnalyticsCalendar;
use Espo\Modules\StudioManagement\Tools\Mq\MqConfig;
use Espo\Modules\StudioManagement\Tools\Mq\MqTaskService;

final class GetAnalytics implements Action
{
    public function __construct(
        private Acl $acl,
        private AnalyticsService $service,
        private User $user,
        private MqConfig $mqConfig,
        private MqTaskService $mqTasks,
        private AnalyticsCalendar $calendar,
    ) {}

    public function process(Request $request): Response
    {
        $canReadStudio = $this->acl->checkScope('SmAnalytics') &&
            $this->acl->check(SmShiftFinancialSnapshot::ENTITY_TYPE, Acl\Table::ACTION_READ);
        if (
            !$canReadStudio
        ) {
            throw new Forbidden();
        }

        $from = (string) ($request->getQueryParam('from') ?? '');
        $to = (string) ($request->getQueryParam('to') ?? '');

        if ($this->mqConfig->isEnabled()) {
            $this->calendar->range($from, $to);
            $task = $this->mqTasks->enqueue(
                MqConfig::ROUTING_ANALYTICS,
                ['from' => $from, 'to' => $to],
                $this->user,
            );

            return ResponseComposer::json([
                'async' => true,
                'taskId' => $task->getId(),
                'status' => $task->get('status'),
            ])->setStatus(202)->setHeader('Cache-Control', 'no-store');
        }

        return ResponseComposer::json($this->service->get($from, $to, $this->user));
    }
}
