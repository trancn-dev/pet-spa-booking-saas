<?php

declare(strict_types=1);

namespace App\Scheduling;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * The only place that converts between the shop's local time and UTC (db-schema 5.4). Each date is
 * converted on its own, so DST days get their real length; never add a fixed offset.
 *
 * Dates are `Y-m-d` shop-local days (a Carbon date counts by its own Y-m-d), times `H:i` / `H:i:s`
 * shop-local wall times.
 */
final class ShopClock
{
    public function __construct(private readonly string $timezone) {}

    public static function for(Tenant $tenant): self
    {
        return new self($tenant->timezone);
    }

    /** The clock of the shop in context (request, job). */
    public static function current(): self
    {
        return self::for(app(TenantContext::class)->require());
    }

    public function timezone(): string
    {
        return $this->timezone;
    }

    /** Today's date at the shop. */
    public function today(?CarbonInterface $now = null): CarbonImmutable
    {
        return CarbonImmutable::instance($now ?? now())->setTimezone($this->timezone)->startOfDay();
    }

    /**
     * The local day `date` as a UTC range [start, end): use it instead of `DATE(column)`.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function localDayRangeUtc(CarbonInterface|string $date): array
    {
        $start = $this->localDate($date);

        return [$start->utc(), $start->addDay()->utc()];
    }

    /** A local wall time on a local date, in UTC. */
    public function toUtc(CarbonInterface|string $date, string $time): CarbonImmutable
    {
        $day = $this->localDate($date)->format('Y-m-d');

        return CarbonImmutable::parse("{$day} {$time}", $this->timezone)->utc();
    }

    /** A UTC instant at the shop. */
    public function toLocal(CarbonInterface|string $instant): CarbonImmutable
    {
        $instant = is_string($instant) ? CarbonImmutable::parse($instant, 'UTC') : CarbonImmutable::instance($instant);

        return $instant->setTimezone($this->timezone);
    }

    /** ISO weekday (1 = Monday … 7 = Sunday) of a local date. */
    public function weekday(CarbonInterface|string $date): int
    {
        return $this->localDate($date)->isoWeekday();
    }

    private function localDate(CarbonInterface|string $date): CarbonImmutable
    {
        $day = is_string($date) ? substr($date, 0, 10) : $date->format('Y-m-d');

        return CarbonImmutable::createFromFormat('!Y-m-d', $day, $this->timezone)
            ?: throw new InvalidArgumentException("Not a date: {$day}");
    }
}
