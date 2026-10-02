<?php

namespace Espo\Modules\StudioManagement\Entities;

use Espo\Core\ORM\Entity;

class SmShift extends Entity
{
    public const ENTITY_TYPE = 'SmShift';
    public const STATUS_DRAFT = 'Draft';
    public const STATUS_OPEN = 'Open';
    public const STATUS_CLOSED = 'Closed';
}
