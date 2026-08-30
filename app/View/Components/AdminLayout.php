<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Makes <x-admin-layout> resolve to resources/views/layouts/admin.blade.php.
 *
 * As with AppLayout, the constructor parameters are what the template can
 * read - a class component does not turn arbitrary attributes into variables.
 */
class AdminLayout extends Component
{
    public function __construct(
        public string $title = 'Admin',
        public ?string $header = null,
    ) {}

    public function render(): View
    {
        return view('layouts.admin');
    }
}
