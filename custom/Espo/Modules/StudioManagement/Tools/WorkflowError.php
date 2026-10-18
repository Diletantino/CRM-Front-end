<?php

namespace Espo\Modules\StudioManagement\Tools;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\Error\Body;

/** UTF-8 JSON is necessary: non-ASCII messages in HTTP headers become garbled. */
final class WorkflowError
{
    public static function badRequest(string $message): BadRequest
    {
        return BadRequest::createWithBody($message, Body::create()->withMessage($message));
    }

    public static function conflict(string $message): Conflict
    {
        return Conflict::createWithBody($message, Body::create()->withMessage($message));
    }

    public static function notFound(string $message): NotFound
    {
        return NotFound::createWithBody($message, Body::create()->withMessage($message));
    }

    public static function forbidden(string $message): Forbidden
    {
        return Forbidden::createWithBody($message, Body::create()->withMessage($message));
    }
}
