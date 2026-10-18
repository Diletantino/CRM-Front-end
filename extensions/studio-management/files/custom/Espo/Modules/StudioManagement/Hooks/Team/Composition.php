<?php

namespace Espo\Modules\StudioManagement\Hooks\Team;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Core\Hook\Hook\AfterSave;
use Espo\Modules\StudioManagement\Tools\TeamComposition;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

class Composition implements BeforeSave, AfterSave
{
    public function __construct(private TeamComposition $composition) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $this->composition->prepare($entity);
    }

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        $this->composition->syncMembership($entity);
    }
}
