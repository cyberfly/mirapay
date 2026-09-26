<?php

use App\Support\Money;

it('formats sen as ringgit', function () {
    expect(Money::format(123450))->toBe('RM1,234.50')
        ->and(Money::format(5))->toBe('RM0.05')
        ->and(Money::format(0))->toBe('RM0.00');
});

it('converts strings to sen without floats', function (string $input, int $sen) {
    expect(Money::toSen($input))->toBe($sen);
})->with([['12.50', 1250], ['12.5', 1250], ['12', 1200], ['1,234.10', 123410], ['0.29', 29], ['19.99', 1999]]);

it('rejects invalid amounts', function () {
    Money::toSen('abc');
})->throws(InvalidArgumentException::class);

it('converts sen to an input string', function () {
    expect(Money::toInput(1250))->toBe('12.50')->and(Money::toInput(5))->toBe('0.05');
});
