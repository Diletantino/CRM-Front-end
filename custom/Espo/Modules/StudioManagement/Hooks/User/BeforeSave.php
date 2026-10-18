<?php

namespace Espo\Modules\StudioManagement\Hooks\User;

use Espo\Core\Hook\Hook\BeforeSave as BeforeSaveHook;
use Espo\Entities\User;
use Espo\Modules\StudioManagement\Tools\AccountTypeResolver;
use Espo\Modules\StudioManagement\Tools\TeamComposition;
use Espo\ORM\EntityManager;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/** @implements BeforeSaveHook<User> */
class BeforeSave implements BeforeSaveHook
{
    public function __construct(private AccountTypeResolver $resolver, private EntityManager $em, private TeamComposition $composition) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($entity instanceof User && !$entity->get('smAccountType')) {
            $entity->set('smAccountType', $this->resolver->resolve($entity));
        }
        if ($entity instanceof User && $entity->isAttributeChanged('teamsIds')) {
            foreach ($entity->get('teamsIds') ?: [] as $id) {
                $team = $this->em->getEntityById('Team', $id);
                if ($team) {
                    $this->composition->checkMember($team, $entity);
                }
            }
        }
    }
}
