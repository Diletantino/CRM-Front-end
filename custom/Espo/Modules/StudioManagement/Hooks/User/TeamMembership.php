<?php

namespace Espo\Modules\StudioManagement\Hooks\User;

use Espo\Core\Hook\Hook\AfterRelate;
use Espo\Core\Hook\Hook\AfterUnrelate;
use Espo\Modules\StudioManagement\Tools\TeamComposition;
use Espo\Modules\StudioManagement\Tools\AccountTypeResolver;
use Espo\ORM\EntityManager;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\RelateOptions;
use Espo\ORM\Repository\Option\UnrelateOptions;

class TeamMembership implements AfterRelate, AfterUnrelate
{
    public function __construct(private TeamComposition $composition, private AccountTypeResolver $types,
        private EntityManager $em) {}

    public function afterRelate(Entity $entity, string $relationName, Entity $relatedEntity, array $columnData, RelateOptions $options): void
    {
        if ($relationName === 'teams') {
            $this->composition->memberChanged($relatedEntity, $entity);
        } elseif ($relationName === 'roles' && !$entity->get('smAccountType')) {
            $stored = $this->em->getEntityById('User', $entity->getId());
            $type = $this->types->resolve($stored);
            if ($type) {
                $stored->set('smAccountType', $type);
                $this->em->saveEntity($stored);
            }
        }
    }

    public function afterUnrelate(Entity $entity, string $relationName, Entity $relatedEntity, UnrelateOptions $options): void
    {
        if ($relationName === 'teams') {
            $this->composition->memberChanged($relatedEntity, $entity, true);
        }
    }
}
