<?php

namespace Espo\Modules\StudioManagement\Classes\Acl\User;

use Espo\Core\Acl\AccessEntityCREDSChecker;
use Espo\Core\Acl\DefaultAccessChecker;
use Espo\Core\Acl\ScopeData;
use Espo\Core\Acl\Traits\DefaultAccessCheckerDependency;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\Modules\StudioManagement\Tools\RolePolicy;

/**
 * Makes the User CRUD matrix authoritative for delegated roles.
 * The system account and super-administrator flag remain protected because
 * deleting either can make an installation unrecoverable.
 *
 * @implements AccessEntityCREDSChecker<User>
 */
final class AccessChecker implements AccessEntityCREDSChecker
{
    use DefaultAccessCheckerDependency;

    public function __construct(private DefaultAccessChecker $defaultAccessChecker, private RolePolicy $policy) {}

    public function check(User $user, ScopeData $data): bool
    {
        return $this->policy->profileLevel($user) !== 'no' || $this->defaultAccessChecker->check($user, $data);
    }

    public function checkRead(User $user, ScopeData $data): bool
    {
        return $this->policy->profileLevel($user) !== 'no' || $this->defaultAccessChecker->checkRead($user, $data);
    }

    public function checkEntityCreate(User $user, Entity $entity, ScopeData $data): bool
    {
        if ($entity instanceof User && $entity->isSuperAdmin() && !$user->isSuperAdmin()) {
            return false;
        }

        return $this->defaultAccessChecker->checkEntityCreate($user, $entity, $data);
    }

    public function checkEntityRead(User $user, Entity $entity, ScopeData $data): bool
    {
        if ($this->policy->profileLevel($user) === 'no') return false;
        if ($entity instanceof User && $entity->isSuperAdmin() && !$user->isSuperAdmin()) {
            return false;
        }

        return true;
    }

    public function checkEntityEdit(User $user, Entity $entity, ScopeData $data): bool
    {
        if ($this->policy->profileLevel($user) !== 'all') return false;
        if (!$entity instanceof User || $entity->isSystem()) {
            return false;
        }

        if ($entity->isSuperAdmin() && !$user->isSuperAdmin()) {
            return false;
        }

        return $this->defaultAccessChecker->checkEntityEdit($user, $entity, $data);
    }

    public function checkEntityDelete(User $user, Entity $entity, ScopeData $data): bool
    {
        if (!$entity instanceof User || $entity->isSystem()) {
            return false;
        }

        if ($entity->isSuperAdmin() && !$user->isSuperAdmin()) {
            return false;
        }

        return $this->defaultAccessChecker->checkEntityDelete($user, $entity, $data);
    }

    public function checkEntityStream(User $user, Entity $entity, ScopeData $data): bool
    {
        return false;
    }
}
