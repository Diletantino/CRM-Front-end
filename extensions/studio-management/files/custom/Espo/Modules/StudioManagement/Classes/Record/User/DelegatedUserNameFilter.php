<?php

namespace Espo\Modules\StudioManagement\Classes\Record\User;

use Espo\Core\Record\Input\Data;
use Espo\Core\Record\Input\Filter;

final class DelegatedUserNameFilter implements Filter
{
    public function filter(Data $data): void
    {
        $value = $data->get('smDelegatedUserName');
        $data->clear('smDelegatedUserName');
        if (!is_string($value)) return;
        $value = trim($value);
        if ($value !== '' && mb_strlen($value) <= 50) $data->set('userName', $value);
    }
}
