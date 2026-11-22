<?php

namespace Espo\Modules\StudioManagement\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Entities\User;
use Espo\Modules\StudioManagement\Tools\RolePolicy;
use Espo\ORM\EntityManager;

final class GetSimpleProfile implements Action
{
    public function __construct(private User $user, private RolePolicy $policy, private EntityManager $em) {}

    public function process(Request $request): Response
    {
        if ($this->policy->profileLevel($this->user) === 'no') throw new Forbidden();
        $id = $request->getRouteParam('id');
        $profile = is_string($id) ? $this->em->getEntityById('User', $id) : null;
        if (!$profile instanceof User || (!$profile->isRegular() && !$profile->isAdmin())) throw new NotFound();
        return ResponseComposer::json([
            'id' => $profile->getId(),
            'userName' => $profile->getUserName(),
            'avatarId' => $profile->get('avatarId'),
        ])->setHeader('Cache-Control', 'no-store');
    }
}
