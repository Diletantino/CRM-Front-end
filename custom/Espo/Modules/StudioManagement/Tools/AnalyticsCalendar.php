<?php

namespace Espo\Modules\StudioManagement\Tools;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Config;

/** Calendar dates use the studio timezone; ORM timestamps remain UTC. */
final class AnalyticsCalendar
{
    public function __construct(private Config $config) {}

    public function timezone(): DateTimeZone
    {
        return new DateTimeZone($this->config->get('timeZone') ?: 'UTC');
    }

    public function range(string $fromRaw, string $toRaw): array
    {
        $zone = $this->timezone();
        $from = DateTimeImmutable::createFromFormat('!Y-m-d', $fromRaw, $zone);
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $toRaw, $zone);
        if (!$from || !$end || $from->format('Y-m-d') !== $fromRaw || $end->format('Y-m-d') !== $toRaw) {
            throw new BadRequest('Dates must use the YYYY-MM-DD format.');
        }
        $to = $end->modify('+1 day');
        if ($from >= $to || $from->diff($to)->days > 732) {
            throw new BadRequest('The date range must be between 1 day and 2 years.');
        }
        return [$from->setTimezone(new DateTimeZone('UTC')), $to->setTimezone(new DateTimeZone('UTC'))];
    }

    public function previousWeek(?DateTimeImmutable $now = null): array
    {
        $now = ($now ?? new DateTimeImmutable('now'))->setTimezone($this->timezone());
        $end = $now->modify('monday this week')->setTime(0, 0);
        $start = $end->modify('-7 days');
        return [
            'from' => $start->format('Y-m-d'),
            'to' => $end->modify('-1 day')->format('Y-m-d'),
            'timeZone' => $this->timezone()->getName(),
        ];
    }
}
