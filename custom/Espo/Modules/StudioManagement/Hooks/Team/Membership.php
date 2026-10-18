<?php

namespace Espo\Modules\StudioManagement\Hooks\Team;

use Espo\Core\Hook\Hook\AfterRelate;
use Espo\Core\Hook\Hook\AfterUnrelate;
use Espo\Entities\User;
use Espo\Modules\StudioManagement\Tools\TeamComposition;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\RelateOptions;
use Espo\ORM\Repository\Option\UnrelateOptions;

class Membership implements AfterRelate, AfterUnrelate
{
    public function __construct(private TeamComposition $composition) {}

    public function afterRelate(Entity $entity, string $relationName, Entity $relatedEntity, array $columnData, RelateOptions $options): void
    {
        if ($relationName === 'users' && $relatedEntity instanceof User) {
            $this->composition->memberChanged($entity, $relatedEntity);
        }
    }

    public function afterUnrelate(Entity $entity, string $relationName, Entity $relatedEntity, UnrelateOptions $options): void
    {
        if ($relationName === 'users' && $relatedEntity instanceof User) {
            $this->composition->memberChanged($entity, $relatedEntity, true);
        }
    }
}
