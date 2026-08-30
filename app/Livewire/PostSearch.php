<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Post;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Live search box.
 *
 * why Livewire here and nowhere else: search results should appear as you
 * type. Doing that with a full page reload per keystroke is absurd, and
 * writing a JSON endpoint plus fetch() plus rendering by hand would duplicate
 * the Blade card markup in JavaScript. Livewire re-renders the same Blade
 * partial on the server and swaps in the HTML.
 *
 * Everything else on this site (the comment form aside) is a plain Blade form
 * with a normal POST - simpler, works without JavaScript, easier to test.
 *
 * @property-read Collection<int, Post> $results  Livewire computed property,
 *         backed by getResultsProperty() and resolved through __get.
 */
class PostSearch extends Component
{
    /** #[Url] keeps ?q=... in the address bar, so a search is shareable. */
    #[Url(as: 'q', except: '')]
    public string $term = '';

    /** Results only start at two characters, to avoid a query per keystroke. */
    public const MIN_LENGTH = 2;

    /** @return Collection<int, Post> */
    public function getResultsProperty(): Collection
    {
        if (mb_strlen(trim($this->term)) < self::MIN_LENGTH) {
            return collect();
        }

        return Post::query()
            ->published()
            ->search($this->term)
            ->with(['category'])
            ->latest('published_at')
            ->limit(6)
            ->get();
    }

    public function clear(): void
    {
        $this->term = '';
    }

    public function render(): View
    {
        return view('livewire.post-search', [
            'results' => $this->results,
            'hasTerm' => mb_strlen(trim($this->term)) >= self::MIN_LENGTH,
        ]);
    }
}
