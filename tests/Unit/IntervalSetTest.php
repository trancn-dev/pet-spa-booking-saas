<?php

declare(strict_types=1);

use App\Scheduling\Interval;
use App\Scheduling\IntervalSet;

/*
| Task 5.4: interval arithmetic behind capacity. Numbers are minutes for readability.
*/

$set = fn (array ...$pairs) => new IntervalSet(array_map(fn (array $p) => new Interval($p[0], $p[1]), $pairs));
$pairs = fn (IntervalSet $s) => array_map(fn (Interval $i) => [$i->start, $i->end], $s->intervals());

it('compares intervals as half-open ranges', function () {
    $a = new Interval(10, 20);

    expect($a->overlaps(new Interval(19, 30)))->toBeTrue()
        ->and($a->overlaps(new Interval(20, 30)))->toBeFalse() // back to back
        ->and($a->contains(new Interval(10, 20)))->toBeTrue()
        ->and($a->contains(new Interval(15, 21)))->toBeFalse()
        ->and($a->intersect(new Interval(15, 40)))->toEqual(new Interval(15, 20))
        ->and($a->intersect(new Interval(20, 40)))->toBeNull()
        ->and($a->shift(5))->toEqual(new Interval(15, 25))
        ->and($a->length())->toBe(10);
});

it('refuses an interval that ends before it starts', function () {
    new Interval(5, 4);
})->throws(InvalidArgumentException::class);

it('sorts and merges overlapping and adjacent pieces, dropping empty ones', function () use ($set, $pairs) {
    expect($pairs($set([50, 60], [10, 20], [15, 30], [30, 40], [70, 70])))->toBe([[10, 40], [50, 60]]);
});

it('subtracts time off from a split shift', function () use ($set, $pairs) {
    $shift = $set([480, 720], [780, 1020]); // 08:00–12:00, 13:00–17:00
    $off = $set([600, 660], [900, 1100]); // 10:00–11:00, 15:00 → after the end

    expect($pairs($shift->subtract($off)))->toBe([[480, 600], [660, 720], [780, 900]])
        ->and($pairs($shift->subtract($set([0, 2000]))))->toBe([])
        ->and($pairs($shift->subtract($set())))->toBe([[480, 720], [780, 1020]]);
});

it('intersects and unions sets', function () use ($set, $pairs) {
    $open = $set([480, 1200]);
    $duty = $set([420, 600], [700, 800], [1150, 1300]);

    expect($pairs($duty->intersect($open)))->toBe([[480, 600], [700, 800], [1150, 1200]])
        ->and($pairs($set([0, 10])->union($set([10, 20], [30, 40]))))->toBe([[0, 20], [30, 40]])
        ->and($duty->intersect($open)->length())->toBe(120 + 100 + 50);
});

it('tells whether a cell is fully covered', function () use ($set) {
    $duty = $set([480, 720], [780, 1020]);

    expect($duty->covers(new Interval(705, 720)))->toBeTrue()
        ->and($duty->covers(new Interval(715, 730)))->toBeFalse() // runs into the break
        ->and($duty->covers(new Interval(780, 795)))->toBeTrue()
        ->and($duty->covers(new Interval(470, 485)))->toBeFalse()
        ->and($set()->covers(new Interval(1, 2)))->toBeFalse();
});
