<?php

namespace Espo\Modules\StudioManagement\Authentication;

use Espo\Core\Api\Request;
use Espo\Core\Authentication\AuthenticationData;
use Espo\Core\Authentication\Hook\OnLogin;
use Espo\Core\Authentication\Result;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\StudioManagement\Tools\RolePolicy;

final class ProfileAccess implements OnLogin
{
    public function __construct(private RolePolicy $policy) {}

    public function process(Result $result, AuthenticationData $data, Request $request): void
    {
        $user = $result->getUser();
        if (!$user) return;
        $path = '/' . trim($request->getResourcePath(), '/');
        if (preg_match('~^/(Stream|GlobalStream|Note)(?:/|$)|/(stream|updateStream|streamAttachments)(?:/|$)~i', $path)) {
            throw new Forbidden();
        }
        if ($user->isAdmin()) return;
        $level = $this->policy->profileLevel($user);
        if (preg_match('~^/(User|PortalUser|ApiUser)/[^/]+~', $path)) {
            if ($level === 'no' || ($level !== 'all' &&
                ($request->getMethod() !== 'GET' || !preg_match('~^/User/[^/]+$~D', $path)))) {
                throw new Forbidden();
            }
        }
    }
}
