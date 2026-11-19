<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Shares common props (app name, flash messages) with every Inertia page.
 */
class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'appName' => 'Voxelion',
            'flash' => [
                'success' => $request->session()->get('success'),
            ],
        ]);
    }
}
