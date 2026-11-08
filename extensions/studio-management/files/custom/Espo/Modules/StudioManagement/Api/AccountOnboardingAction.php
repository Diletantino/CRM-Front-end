<?php

namespace Espo\Modules\StudioManagement\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\NotFound;
use Espo\Entities\User;
use Espo\Modules\StudioManagement\Tools\AccountOnboarding;

final class AccountOnboardingAction implements Action
{
    public function __construct(private AccountOnboarding $accounts, private User $user) {}

    public function process(Request $request): Response
    {
        $id = $request->getRouteParam('id');
        $photo = $request->getRouteParam('photoId');
        $path = $request->getResourcePath();
        $data = match ($request->getMethod()) {
            'GET' => match (true) {
                $id === null => $this->accounts->getList($this->user, max(0, (int) $request->getQueryParam('offset'))),
                $photo !== null => $this->accounts->getPhoto($this->user, $id, $photo),
                default => $this->accounts->getProfile($this->user, $id),
            },
            'PUT' => $this->accounts->save($this->user, $id, $request->getParsedBody(), false),
            'POST' => str_ends_with($path, '/complete')
                ? $this->accounts->save($this->user, $id, $request->getParsedBody(), true)
                : $this->accounts->uploadPhoto($this->user, $id, $request->getParsedBody()),
            'DELETE' => $this->accounts->removePhoto($this->user, $id, $photo),
            default => throw new NotFound(),
        };
        return ResponseComposer::json($data)->setHeader('Cache-Control', 'no-store');
    }
}
