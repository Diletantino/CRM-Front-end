<?php

namespace Espo\Modules\StudioManagement\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\NotFound;
use Espo\Modules\StudioManagement\Tools\RegistrationService;

final class GetInvitation implements Action
{
    private const HEADER_LINK_TOKEN = 'X-Studio-Access-Token';

    public function __construct(private RegistrationService $service) {}

    public function process(Request $request): Response
    {
        $token = trim((string) $request->getHeader(self::HEADER_LINK_TOKEN));
        $info = $this->service->getInvitationInfo($token);

        if ($info === null) {
            throw new NotFound('Personal link not found.');
        }

        return ResponseComposer::json($info);
    }
}
