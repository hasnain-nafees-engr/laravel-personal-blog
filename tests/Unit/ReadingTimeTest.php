<?php

use App\Support\ReadingTime;

it('returns zero for empty text', function () {
    expect(ReadingTime::forText(''))->toBe(0)
        ->and(ReadingTime::forText('   '))->toBe(0);
});

it('rounds up to at least one minute for short text', function () {
    expect(ReadingTime::forText('Just a handful of words here.'))->toBe(1);
});

it('scales with the number of words', function () {
    // 200 words per minute is the configured default, so 600 words = 3 minutes.
    $text = str_repeat('word ', 600);

    expect(ReadingTime::forText($text))->toBe(3);
});

it('ignores html tags when counting', function () {
    $plain = str_repeat('word ', 200);
    $wrapped = '<p>'.str_repeat('<strong>word</strong> ', 200).'</p>';

    expect(ReadingTime::forText($wrapped))->toBe(ReadingTime::forText($plain));
});
