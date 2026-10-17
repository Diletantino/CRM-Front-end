<?php

namespace Espo\Modules\StudioManagement\Hooks\User;

use Espo\Core\Hook\Hook\BeforeSave as BeforeSaveHook;
use Espo\Entities\User;
use Espo\Modules\StudioManagement\Tools\AccountTypeResolver;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/** @implements BeforeSaveHook<User> */
class BeforeSave implements BeforeSaveHook
{
    public function __construct(private AccountTypeResolver $resolver) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($entity instanceof User && !$entity->get('smAccountType')) {
            $entity->set('smAccountType', $this->resolver->resolve($entity));
        }
    }
}
