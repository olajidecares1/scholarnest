<?php

use App\Support\Mark;

test('a mark keeps its whole-number zeros and loses only trailing decimal zeros', function (mixed $value, string $expected, int $decimals = 2, bool $thousands = false) {
    expect(Mark::format($value, $decimals, $thousands))->toBe($expected);
})->with([
    'total of 200' => [200, '200'],
    'total of 200.0 float' => [200.0, '200'],
    'decimal cast string' => ['60.00', '60'],
    'half mark' => ['62.50', '62.5'],
    'ten' => ['10.00', '10'],
    'zero' => [0, '0'],
    'thousands' => [1300.0, '1,300', 2, true],
    'thousands with decimal' => [1298.5, '1,298.5', 2, true],
    'one decimal place' => [80.0, '80', 1, true],
]);
