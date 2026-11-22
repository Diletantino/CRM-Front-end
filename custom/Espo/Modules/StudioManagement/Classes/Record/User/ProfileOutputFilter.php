<?php

namespace Espo\Modules\StudioManagement\Classes\Record\User;

use Espo\Core\Record\Output\Filter;
use Espo\Entities\User;
use Espo\Modules\StudioManagement\Tools\RolePolicy;
use Espo\ORM\Entity;

/** @implements Filter<User> */
final class ProfileOutputFilter implements Filter
{
    public function __construct(private User $user, private RolePolicy $policy) {}

    public function filter(Entity $entity): void
    {
        $entity->clear('smProfileComments');
        if ($this->policy->profileLevel($this->user) === 'all') return;
        foreach (array_keys((array) $entity->getValueMap()) as $attribute) {
            if (!in_array($attribute, ['id', 'name', 'userName', 'avatarId', 'avatarColor'], true)) {
                $entity->clear($attribute);
            }
        }
        $entity->set('name', $entity->get('userName'));
    }
}
