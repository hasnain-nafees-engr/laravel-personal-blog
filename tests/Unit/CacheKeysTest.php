<?php

use App\Support\CacheKeys;

it('lists every key a post change must invalidate', function () {
    $keys = CacheKeys::postRelated();

    expect($keys)
        ->toContain(CacheKeys::FEATURED_POST)
        ->toContain(CacheKeys::SIDEBAR_CATEGORIES)
        ->toContain(CacheKeys::SIDEBAR_TAGS)
        ->toContain(CacheKeys::DASHBOARD_COUNTS);
});

it('uses unique, namespaced keys', function () {
    $keys = CacheKeys::postRelated();

    expect($keys)->toHaveCount(count(array_unique($keys)));

    foreach ($keys as $key) {
        expect($key)->toStartWith('blog:');
    }
});
