<?php

declare(strict_types=1);

namespace App\Scheduling;

/**
 * A union of disjoint, sorted intervals: a person's time on duty (slots minus time off), a
 * branch's opening hours over several days. Adjacent and overlapping pieces are merged.
 */
final class IntervalSet
{
    /** @var list<Interval> */
    private array $intervals;

    /**
     * @param  iterable<Interval>  $intervals
     */
    public function __construct(iterable $intervals = [])
    {
        $list = [];
        foreach ($intervals as $interval) {
            if (! $interval->isEmpty()) {
                $list[] = $interval;
            }
        }
        usort($list, fn (Interval $a, Interval $b) => $a->start <=> $b->start ?: $a->end <=> $b->end);

        $merged = [];
        foreach ($list as $interval) {
            $last = $merged === [] ? null : $merged[array_key_last($merged)];
            if ($last !== null && $interval->start <= $last->end) {
                $merged[array_key_last($merged)] = new Interval($last->start, max($last->end, $interval->end));
            } else {
                $merged[] = $interval;
            }
        }

        $this->intervals = $merged;
    }

    /**
     * @return list<Interval>
     */
    public function intervals(): array
    {
        return $this->intervals;
    }

    public function isEmpty(): bool
    {
        return $this->intervals === [];
    }

    public function union(self $other): self
    {
        return new self([...$this->intervals, ...$other->intervals]);
    }

    public function subtract(self $other): self
    {
        $result = [];
        foreach ($this->intervals as $interval) {
            $pieces = [$interval];
            foreach ($other->intervals as $cut) {
                if ($cut->start >= $interval->end) {
                    break;
                }
                $next = [];
                foreach ($pieces as $piece) {
                    if (! $piece->overlaps($cut)) {
                        $next[] = $piece;

                        continue;
                    }
                    if ($piece->start < $cut->start) {
                        $next[] = new Interval($piece->start, $cut->start);
                    }
                    if ($cut->end < $piece->end) {
                        $next[] = new Interval($cut->end, $piece->end);
                    }
                }
                $pieces = $next;
            }
            array_push($result, ...$pieces);
        }

        return new self($result);
    }

    public function intersect(self $other): self
    {
        $result = [];
        $i = $j = 0;
        while ($i < count($this->intervals) && $j < count($other->intervals)) {
            $a = $this->intervals[$i];
            $b = $other->intervals[$j];
            if (($overlap = $a->intersect($b)) !== null) {
                $result[] = $overlap;
            }
            $a->end < $b->end ? $i++ : $j++;
        }

        return new self($result);
    }

    /** Whether the whole of `$interval` lies inside the set. */
    public function covers(Interval $interval): bool
    {
        foreach ($this->intervals as $piece) {
            if ($piece->start > $interval->start) {
                return false;
            }
            if ($piece->contains($interval)) {
                return true;
            }
        }

        return false;
    }

    public function length(): int
    {
        return array_sum(array_map(fn (Interval $i) => $i->length(), $this->intervals));
    }
}
