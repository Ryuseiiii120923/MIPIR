<?php

use App\Inspection\Support\PartNumber;

it('gets the base part number', function (string $input, string $expected) {
    expect(PartNumber::base($input))->toBe($expected);
})->with([
    ['F00V C38001-5',        'F00V C38001'],
    ['F00V C38001',          'F00V C38001'],
    ['135659-0010',          '135659-0010'],
    ['F00V.C38.005N',   'F00V.C38.005'],
    ['F00V.C38.005-5', 'F00V.C38.005'],
    ['F00VE2404C ChP', 'F00VE2404C']
]);