<?php

namespace Espo\Modules\StudioManagement\Tools;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Config;

final class RegistrationLinkUrl
{
    public function __construct(private Config $config) {}

    public function build(string $token): string
    {
        $siteUrl = rtrim((string) $this->config->get('siteUrl'), '/');

        if ($siteUrl === '') {
            throw new BadRequest('The EspoCRM siteUrl setting is required.');
        }

        // URL fragments are never sent in HTTP requests or Referer headers.
        return $siteUrl . '/?entryPoint=studioRegister#' . rawurlencode($token);
    }
}
