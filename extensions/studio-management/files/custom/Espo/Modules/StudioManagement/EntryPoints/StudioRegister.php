<?php

namespace Espo\Modules\StudioManagement\EntryPoints;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\EntryPoint\EntryPoint;
use Espo\Core\EntryPoint\Traits\NoAuth;
use Espo\Core\Utils\Client\ActionRenderer;
use Espo\Modules\StudioManagement\Tools\RegistrationService;

final class StudioRegister implements EntryPoint
{
    use NoAuth;

    public function __construct(
        private ActionRenderer $actionRenderer,
        private RegistrationService $service,
    ) {}

    public function run(Request $request, Response $response): void
    {
        $token = (string) ($request->getQueryParam('token') ?? '');
        $info = $this->service->getInvitationInfo($token);

        $params = new ActionRenderer\Params(
            'studio-management:controllers/registration',
            'register',
            [
                'token' => $token,
                'invitation' => $info,
                'notFound' => $info === null,
            ]
        );

        $this->actionRenderer->write($response, $params);
    }
}
