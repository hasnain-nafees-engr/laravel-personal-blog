<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Makes <x-admin-layout> resolve to resources/views/layouts/admin.blade.php.
 *
 * why a class for a one-line render(): it is the convention Breeze already
 * uses for <x-app-layout>, so both layouts are found the same way. Anything
 * passed as an attribute (title="Posts") arrives in the template as $title.
 */
class AdminLayout extends Component
{
    public function render(): View
    {
        return view('layouts.admin');
    }
}
