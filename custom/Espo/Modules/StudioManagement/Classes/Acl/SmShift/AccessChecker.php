<?php

namespace Espo\Modules\StudioManagement\Classes\Acl\SmShift;

use Espo\Core\Acl\AccessEntityCREDSChecker;
use Espo\Core\Acl\DefaultAccessChecker;
use Espo\Core\Acl\ScopeData;
use Espo\Core\Acl\Traits\DefaultAccessCheckerDependency;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\Modules\StudioManagement\Entities\SmShift;
use Espo\Modules\StudioManagement\Tools\RolePolicy;

/** @implements AccessEntityCREDSChecker<SmShift> */
final class AccessChecker implements AccessEntityCREDSChecker
{
    use DefaultAccessCheckerDependency;

    public function __construct(DefaultAccessChecker $defaultAccessChecker, private RolePolicy $policy)
    {
        $this->defaultAccessChecker = $defaultAccessChecker;
    }

    public function checkEntityRead(User $user, Entity $entity, ScopeData $data): bool
    {
        if (!$this->defaultAccessChecker->checkEntityRead($user, $entity, $data)) return false;
        if (!$user->isAdmin() && $user->get('smAccountType') === 'Operator' &&
            !$this->policy->canRevise($user, $entity)) {
            return $entity->get('operatorId') === $user->getId() &&
                $entity->get('status') !== SmShift::STATUS_CLOSED;
        }
        return true;
    }

    public function checkEntityStream(User $user, Entity $entity, ScopeData $data): bool
    {
        return $this->checkEntityRead($user, $entity, $data) &&
            $this->defaultAccessChecker->checkEntityStream($user, $entity, $data);
    }

    public function checkEntityEdit(User $user, Entity $entity, ScopeData $data): bool
    {
        if (!$this->defaultAccessChecker->checkEntityEdit($user, $entity, $data)) return false;
        if (!$user->isAdmin() && $user->get('smAccountType') === 'Operator' &&
            $entity->get('operatorId') !== $user->getId() && !$this->policy->canRevise($user, $entity)) return false;
        return match ($entity->get('status')) {
            SmShift::STATUS_CLOSED => $this->policy->canRevise($user, $entity),
            SmShift::STATUS_REVIEW => $this->policy->canReview($user, $entity),
            default => true,
        };
    }
}
