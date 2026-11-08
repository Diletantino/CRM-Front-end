<?php

namespace Espo\Modules\StudioManagement\Authentication;

use Espo\Core\Api\Request;
use Espo\Core\Authentication\AuthenticationData;
use Espo\Core\Authentication\Hook\OnLogin;
use Espo\Core\Authentication\Result;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\StudioManagement\Tools\AccountOnboarding;

final class RecruiterAccess implements OnLogin
{
    public function process(Result $result, AuthenticationData $data, Request $request): void
    {
        $user = $result->getUser();
        if (!$user || !AccountOnboarding::isRecruiter($user)) {
            return;
        }
        $path = '/' . trim($request->getResourcePath(), '/');
        $method = $request->getMethod();
        $id = '[A-Za-z0-9_-]+';
        $queue = '/StudioManagement/onboarding';
        // Espo performs an authenticated GET of its API root before opening the UI.
        $sessionCheck = $path === '/' && str_ends_with(rtrim($request->getUri()->getPath(), '/'), '/api/v1');
        $allowed = match ($method) {
            'GET' => $sessionCheck || in_array($path, ['/App/user', '/Settings', '/Metadata', '/I18n',
                '/Preferences/' . $user->getId(), $queue], true) ||
                preg_match("~^$queue/$id(?:/photos/$id)?$~D", $path),
            'PUT' => preg_match("~^$queue/$id$~D", $path),
            'POST' => $path === '/App/destroyAuthToken' || preg_match("~^$queue/$id/(complete|photos)$~D", $path),
            'DELETE' => preg_match("~^$queue/$id/photos/$id$~D", $path),
            default => false,
        };
        if (!$allowed) {
            throw new Forbidden();
        }
    }
}
