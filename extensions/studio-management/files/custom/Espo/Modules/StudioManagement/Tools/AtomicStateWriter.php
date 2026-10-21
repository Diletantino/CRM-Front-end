<?php

namespace Espo\Modules\StudioManagement\Tools;

use Espo\Core\Utils\Config\ConfigWriterFileManager;

/**
 * Concurrent role saves can trigger ConfigWriter's non-atomic verification fallback.
 * Readers do not hold that writer's flock and can include a partially written state.php.
 * Keep the native unique-temp-file + rename path for state updates, including retries.
 * Other config files keep their original behavior.
 */
final class AtomicStateWriter extends ConfigWriterFileManager
{
    public function putPhpContentsNoRenaming(string $path, array $data): void
    {
        if (basename($path) === 'state.php') {
            parent::putPhpContents($path, $data);
            return;
        }
        parent::putPhpContentsNoRenaming($path, $data);
    }
}
