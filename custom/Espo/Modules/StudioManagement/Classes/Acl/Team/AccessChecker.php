<?php

namespace Espo\Modules\StudioManagement\Classes\Acl\Team;

use Espo\Core\Acl\AccessEntityCREDSChecker;
use Espo\Core\Acl\DefaultAccessChecker;
use Espo\Core\Acl\ScopeData;
use Espo\Core\Acl\Traits\DefaultAccessCheckerDependency;
use Espo\Entities\User;
use Espo\ORM\Entity;

/** @implements AccessEntityCREDSChecker<\Espo\Entities\Team> */
final class AccessChecker implements AccessEntityCREDSChecker
{
    use DefaultAccessCheckerDependency;

    public function __construct(DefaultAccessChecker $defaultAccessChecker)
    {
        $this->defaultAccessChecker = $defaultAccessChecker;
    }

    private function isRestrictedModel(User $user, ScopeData $data): bool
    {
        return !$user->isAdmin() && $user->get('smAccountType') === 'Model' && $data->getRead() !== 'all';
    }

    public function check(User $user, ScopeData $data): bool
    {
        return !$this->isRestrictedModel($user, $data) && $this->defaultAccessChecker->check($user, $data);
    }

    public function checkRead(User $user, ScopeData $data): bool
    {
        return !$this->isRestrictedModel($user, $data) && $this->defaultAccessChecker->checkRead($user, $data);
    }

    public function checkEntityRead(User $user, Entity $entity, ScopeData $data): bool
    {
        return !$this->isRestrictedModel($user, $data) && $this->defaultAccessChecker->checkEntityRead($user, $entity, $data);
    }
}
