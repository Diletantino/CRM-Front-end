<?php

namespace Espo\Modules\StudioManagement\Classes\Acl\Attachment;

use Espo\Classes\Acl\Attachment\AccessChecker as BaseChecker;
use Espo\Core\Acl\DefaultAccessChecker;
use Espo\Core\Acl\ScopeData;
use Espo\Core\AclManager;
use Espo\Entities\User;
use Espo\Modules\StudioManagement\Tools\RolePolicy;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

final class AccessChecker extends BaseChecker
{
    public function __construct(DefaultAccessChecker $default, AclManager $acl, EntityManager $em, private RolePolicy $policy)
    {
        parent::__construct($default, $acl, $em);
    }

    public function checkEntityRead(User $user, Entity $entity, ScopeData $data): bool
    {
        if (in_array('User', [$entity->get('parentType'), $entity->get('relatedType')], true) &&
            $this->policy->profileLevel($user) !== 'all') {
            return $this->policy->profileLevel($user) !== 'no' && $entity->get('field') === 'avatar';
        }
        return parent::checkEntityRead($user, $entity, $data);
    }
}
