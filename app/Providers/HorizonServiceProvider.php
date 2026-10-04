<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    protected function authorization(): void
    {
        $this->gate();

        Horizon::auth(function (Request $request): bool {
            $user = $request->user() ?? Auth::guard('sanctum')->user();

            return $user instanceof User && $user->is_admin;
        });
    }

    protected function gate(): void
    {
        Gate::define('viewHorizon', function (?User $user = null): bool {
            return $user instanceof User && $user->is_admin;
        });
    }
}
