<?php

namespace Espo\Modules\StudioManagement\Api;

use Espo\Entities\User;
use Espo\Modules\StudioManagement\Tools\RolePolicy;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class SessionProfileFilter implements MiddlewareInterface
{
    public function __construct(private User $user, private RolePolicy $policy) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        if ($response->getStatusCode() !== 200) return $response;
        $data = json_decode((string) $response->getBody());
        if (!isset($data->user)) return $response;
        unset($data->user->smProfileComments);
        // Preserve session identity/ACL inputs, while omitting profile and payroll fields.
        foreach (array_keys((array) $data->user) as $field) {
            if ($this->policy->profileLevel($this->user) === 'all') break;
            if ((str_starts_with($field, 'sm') && $field !== 'smAccountType') ||
                in_array($field, ['title', 'phoneNumber', 'phoneNumberData', 'emailAddressData'], true)) {
                unset($data->user->$field);
            }
        }
        return $response->withBody(Utils::streamFor(json_encode($data, JSON_THROW_ON_ERROR)));
    }
}
