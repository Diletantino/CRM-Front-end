<?php

namespace Espo\Modules\StudioManagement\Classes\Select\User\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\ORM\Query\SelectBuilder;

final class Models implements Filter
{
    public function apply(SelectBuilder $queryBuilder): void
    {
        $queryBuilder->where([
            'isActive' => true,
            'smAccountType' => 'Model',
        ]);
    }
}
