<?php

declare(strict_types=1);

use App\Support\Phone;

it('normalises Vietnamese numbers to E.164', function (string $input, string $e164) {
    expect(Phone::parse($input)->e164())->toBe($e164);
})->with([
    ['0901234567', '+84901234567'],
    ['0901 234 567', '+84901234567'],
    ['090-123-4567', '+84901234567'],
    ['090.123.4567', '+84901234567'],
    ['84901234567', '+84901234567'],
    ['+84 901 234 567', '+84901234567'],
    ['+84901234567', '+84901234567'],
    ['0386 123 456', '+84386123456'],
    ['(024) 3826 1234', '+842438261234'],
]);

it('rejects what is not a Vietnamese phone', function (?string $input) {
    expect(Phone::tryParse($input))->toBeNull()
        ->and(Phone::isValid($input))->toBeFalse();
})->with([
    null, '', '   ', '12345', '090123456', '09012345678', '0101234567', '+1 415 555 0100',
    '0901abc567', '901234567', 'không có',
]);

it('formats for people and keeps the last four digits', function () {
    $mobile = Phone::parse('+84901234567');
    $landline = Phone::parse('02438261234');

    expect($mobile->display())->toBe('0901 234 567')
        ->and($landline->display())->toBe('024 3826 1234')
        ->and($mobile->suffix())->toBe('4567')
        ->and(Phone::format('+84901234567'))->toBe('0901 234 567')
        ->and(Phone::format(null))->toBeNull()
        ->and($mobile->equals(Phone::parse('0901 234 567')))->toBeTrue()
        ->and((string) $mobile)->toBe('+84901234567');
});

it('throws on parse of an invalid number', function () {
    Phone::parse('123');
})->throws(InvalidArgumentException::class, Phone::INVALID);
