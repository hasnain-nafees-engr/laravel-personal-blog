<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // why: since Laravel 11 the base controller is empty, so $this->authorize()
    // does not exist until you add this trait. Putting it here means every
    // controller can call authorize() and get a 403 (not a fatal error) when a
    // policy says no.
    use AuthorizesRequests;
}
