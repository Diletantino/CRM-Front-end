<?php

namespace Espo\Modules\StudioManagement\Tools;

use Espo\Core\Api\Request;
use Espo\Core\Api\Util;
use Espo\Core\Authentication\Util\IpAddressUtil;
use Espo\Core\Utils\Config;

final class AccessPolicy
{
    public function __construct(
        private Config $config,
        private Util $apiUtil,
        private IpAddressUtil $ipAddressUtil,
    ) {}

    public function isAdminRequestAllowed(Request $request): bool
    {
        $host = mb_strtolower(rtrim($request->getUri()->getHost(), '.'));
        $ipAddress = $this->apiUtil->obtainIpFromRequest($request);
        $allowedHosts = $this->parseList($this->config->get('studioAdminHosts'));
        $allowedIps = $this->parseList($this->config->get('studioAdminAllowedIps'));

        if ($host === '' || !$ipAddress || $allowedHosts === [] || $allowedIps === []) {
            return false;
        }

        if (!in_array($host, array_map('mb_strtolower', $allowedHosts), true)) {
            return false;
        }

        return $this->ipAddressUtil->isInWhitelist($ipAddress, $allowedIps);
    }

    /** @return string[] */
    private function parseList(mixed $value): array
    {
        if (is_array($value)) {
            $items = $value;
        } elseif (is_string($value)) {
            $items = explode(',', $value);
        } else {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $item): string => trim((string) $item),
            $items,
        )));
    }
}
