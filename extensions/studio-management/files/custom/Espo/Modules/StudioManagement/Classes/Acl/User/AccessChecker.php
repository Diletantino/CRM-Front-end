<?php

namespace Espo\Modules\StudioManagement\Classes\Acl\User;

use Espo\Core\Acl\AccessEntityCREDSChecker;
use Espo\Core\Acl\DefaultAccessChecker;
use Espo\Core\Acl\ScopeData;
use Espo\Core\Acl\Traits\DefaultAccessCheckerDependency;
use Espo\Entities\User;
use Espo\ORM\Entity;

/** @implements AccessEntityCREDSChecker<User> */
final class AccessChecker implements AccessEntityCREDSChecker
{
    use DefaultAccessCheckerDependency;

    public function __construct(private DefaultAccessChecker $defaultAccessChecker) {}

    public function checkEntityCreate(User $user, Entity $entity, ScopeData $data): bool
    {
        if ($entity instanceof User && $entity->isSuperAdmin() && !$user->isSuperAdmin()) return false;
        return $this->defaultAccessChecker->checkEntityCreate($user, $entity, $data);
    }

    public function checkEntityRead(User $user, Entity $entity, ScopeData $data): bool
    {
        if ($entity instanceof User && $entity->isSuperAdmin() && !$user->isSuperAdmin()) return false;
        return $this->defaultAccessChecker->checkEntityRead($user, $entity, $data);
    }

    public function checkEntityEdit(User $user, Entity $entity, ScopeData $data): bool
    {
        if (!$entity instanceof User || $entity->isSystem()) return false;
        if ($entity->isSuperAdmin() && !$user->isSuperAdmin()) return false;
        return $this->defaultAccessChecker->checkEntityEdit($user, $entity, $data);
    }

    public function checkEntityDelete(User $user, Entity $entity, ScopeData $data): bool
    {
        if (!$entity instanceof User || $entity->isSystem()) return false;
        if ($entity->isSuperAdmin() && !$user->isSuperAdmin()) return false;
        return $this->defaultAccessChecker->checkEntityDelete($user, $entity, $data);
    }

    public function checkEntityStream(User $user, Entity $entity, ScopeData $data): bool
    {
        return $this->defaultAccessChecker->checkEntityStream($user, $entity, $data);
    }
}
