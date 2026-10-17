<?php

namespace Espo\Modules\StudioManagement\Tools;

use Espo\ORM\Entity;

final class ModelShiftDefaults
{
    /** @return array<string, mixed> */
    public function get(Entity $model): array
    {
        $rows = $model->get('smSiteData') ?: [];
        if (!$rows) {
            // Preserve existing profiles using the original "CB - login - pass" format.
            foreach (preg_split('/\R/u', (string) $model->get('smSiteAccess')) ?: [] as $line) {
                $parts = preg_split('/\s+[-–—]\s+/u', trim($line), 3);
                if (count($parts) === 3) {
                    $rows[] = ['site' => $parts[0], 'login' => $parts[1], 'password' => $parts[2]];
                }
            }
        }
        $result = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            $result[] = [
                'site' => (string) ($row['site'] ?? ''),
                'login' => (string) ($row['login'] ?? ''),
                'password' => (string) ($row['password'] ?? ''),
                'state' => 'Pending', 'earning' => '0.00', 'offlineBonus' => '0.00',
            ];
        }
        return [
            'siteData' => $result,
            'modelBirthDate' => $model->get('smBirthDate'),
        ];
    }
}
