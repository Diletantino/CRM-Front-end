<?php

namespace Espo\Modules\StudioManagement\Classes\RecordHooks;

use Espo\Core\Record\Hook\LinkHook;
use Espo\Entities\User;
use Espo\Modules\StudioManagement\Tools\TeamComposition;
use Espo\ORM\Entity;

/** Called for both Team.users and User.teams native relationship endpoints. */
class TeamMember implements LinkHook
{
    public function __construct(private TeamComposition $composition) {}

    public function process(Entity $entity, string $link, Entity $foreignEntity): void
    {
        if ($entity->getEntityType() === 'Team' && $link === 'users' && $foreignEntity instanceof User) {
            $this->composition->checkMember($entity, $foreignEntity);
        } elseif ($entity instanceof User && $link === 'teams') {
            $this->composition->checkMember($foreignEntity, $entity);
        }
    }
}
