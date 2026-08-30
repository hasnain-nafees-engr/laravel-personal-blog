<?php

namespace App\View\Components;

use Illuminate\Support\Carbon;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * The public site layout, <x-app-layout>.
 *
 * why the constructor lists every prop: a CLASS-based Blade component only
 * passes its CONSTRUCTOR PARAMETERS into the template as variables. Anything
 * else lands in $attributes and is invisible to `$title`. Breeze's stub had
 * no constructor, so `<x-app-layout :title="...">` silently did nothing and
 * every page rendered the same <title> - which is exactly what the SEO test
 * caught.
 *
 * Blade converts kebab-case attributes to camelCase parameters, so
 * `og-type="article"` arrives as $ogType and `:published-at` as $publishedAt.
 */
class AppLayout extends Component
{
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?string $image = null,
        public string $ogType = 'website',
        public Carbon|string|null $publishedAt = null,
        public bool $isPreview = false,
    ) {}

    public function render(): View
    {
        return view('layouts.app');
    }
}
