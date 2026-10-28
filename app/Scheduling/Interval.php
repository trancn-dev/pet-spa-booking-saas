<?php

declare(strict_types=1);

namespace App\Scheduling;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * A half-open time range [start, end) in UTC epoch seconds. Integers keep capacity maths exact
 * and fast (task 5.4 computes thousands of 15-minute cells per request).
 */
final readonly class Interval
{
    public function __construct(public int $start, public int $end)
    {
        if ($end < $start) {
            throw new InvalidArgumentException("Interval ends before it starts: {$start} > {$end}");
        }
    }

    public static function of(DateTimeInterface $start, DateTimeInterface $end): self
    {
        return new self($start->getTimestamp(), $end->getTimestamp());
    }

    public function length(): int
    {
        return $this->end - $this->start;
    }

    public function isEmpty(): bool
    {
        return $this->end === $this->start;
    }

    public function overlaps(self $other): bool
    {
        return $this->start < $other->end && $other->start < $this->end;
    }

    public function contains(self $other): bool
    {
        return $this->start <= $other->start && $other->end <= $this->end;
    }

    public function intersect(self $other): ?self
    {
        $start = max($this->start, $other->start);
        $end = min($this->end, $other->end);

        return $start < $end ? new self($start, $end) : null;
    }

    public function shift(int $seconds): self
    {
        return new self($this->start + $seconds, $this->end + $seconds);
    }

    public function startsAt(): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestampUTC($this->start);
    }

    public function endsAt(): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestampUTC($this->end);
    }
}
