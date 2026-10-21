<?php

namespace Espo\Modules\StudioManagement\Tools;

use Espo\Core\Utils\DataCache;
use Espo\Core\Utils\File\Exceptions\FileError;
use Espo\Core\Utils\Cache\Exceptions\ReadError;
use stdClass;

/**
 * Espo 10.0 checks has() then reads get(); a simultaneous Role save can remove
 * the file in between. Pin a successfully read ACL entry for this request only.
 * Missing/corrupted ACL entries are cache misses, never permissive fallbacks.
 * All non-ACL caches retain the native behavior.
 */
final class StableAclCache extends DataCache
{
    private array $pinned = [];

    private function isAcl(string $key): bool
    {
        return str_starts_with($key, 'acl/') || str_starts_with($key, 'aclMap/');
    }

    public function has(string $key): bool
    {
        if (!$this->isAcl($key)) {
            return parent::has($key);
        }
        if (array_key_exists($key, $this->pinned)) {
            return true;
        }
        if (!parent::has($key)) {
            return false;
        }
        try {
            $value = parent::get($key);
        } catch (FileError | ReadError) {
            return false;
        }
        if (!is_array($value) && !$value instanceof stdClass) {
            return false;
        }
        $this->pinned[$key] = $value;
        return true;
    }

    public function get(string $key): array|stdClass|null
    {
        return $this->isAcl($key) && array_key_exists($key, $this->pinned) ?
            $this->pinned[$key] : parent::get($key);
    }

    public function clear(string $key): void
    {
        unset($this->pinned[$key]);
        parent::clear($key);
    }
}
