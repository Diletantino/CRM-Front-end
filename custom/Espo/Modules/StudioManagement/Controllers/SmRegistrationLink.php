<?php

namespace Espo\Modules\StudioManagement\Controllers;

use Espo\Core\Controllers\Record;

class SmRegistrationLink extends Record
{
    protected function checkAccess(): bool
    {
        return $this->user->isAdmin();
    }
}
