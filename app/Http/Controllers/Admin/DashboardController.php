<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Comment;
use App\Services\BlogQueries;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly BlogQueries $queries) {}

    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'counts' => $this->queries->dashboardCounts(),

            // The polymorphic relation in action: one query returns actions
            // on posts AND comments, and `with('subject')` eager-loads each
            // subject from its own table.
            'activity' => ActivityLog::query()
                ->with(['user', 'subject'])
                ->latest()
                ->limit(8)
                ->get(),

            'pendingComments' => Comment::query()
                ->pending()
                ->with('post')
                ->latest()
                ->limit(5)
                ->get(),
        ]);
    }
}
