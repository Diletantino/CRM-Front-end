<?php

namespace Espo\Modules\StudioManagement\EntryPoints;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\EntryPoint\EntryPoint;
use Espo\Core\EntryPoint\Traits\NoAuth;
use Espo\Core\Utils\Client\ActionRenderer;

final class StudioRegister implements EntryPoint
{
    use NoAuth;

    public function __construct(
        private ActionRenderer $actionRenderer,
    ) {}

    public function run(Request $request, Response $response): void
    {
        $params = new ActionRenderer\Params(
            'studio-management:controllers/registration',
            'register',
            []
        );

        $this->actionRenderer->write($response, $params->withInitAuth());
    }
}
