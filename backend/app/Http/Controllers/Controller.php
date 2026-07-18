<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // Enables $this->authorize(...) in controllers so Policy checks read cleanly.
    use AuthorizesRequests;
}
