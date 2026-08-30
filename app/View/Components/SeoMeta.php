<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\View\Component;

/**
 * Builds the <title>, description, canonical URL and Open Graph tags.
 *
 * why a CLASS-based component and not an anonymous one: there is real logic
 * here - falling back to the site name, absolute-ising an image path, picking
 * an og:type. That logic belongs in PHP where it can be read and tested, not
 * in a template full of ternaries. Compare with x-post-card, which is
 * anonymous because it only arranges markup.
 */
class SeoMeta extends Component
{
    public string $pageTitle;

    public string $pageDescription;

    public ?string $imageUrl;

    public function __construct(
        ?string $title = null,
        ?string $description = null,
        ?string $image = null,
        public string $type = 'website',
        public Carbon|string|null $published = null,
    ) {
        $siteName = config('app.name');

        $this->pageTitle = filled($title) ? "{$title} — {$siteName}" : $siteName;

        $this->pageDescription = str(
            $description ?: 'Notes on engineering, Laravel and building things that last.',
        )->stripTags()->squish()->limit(160)->value();

        // Open Graph requires an ABSOLUTE url - a relative path shows no
        // image at all when the link is pasted into Slack or LinkedIn.
        $this->imageUrl = filled($image)
            ? (str_starts_with($image, 'http') ? $image : asset('storage/'.ltrim($image, '/')))
            : null;
    }

    public function render(): View
    {
        return view('components.seo-meta');
    }
}
