<?php

namespace App\Providers;

use App\Services\HandoffTokenService;
use App\Services\VerificationProvider\VerificationProvider;
use App\Services\VerificationProvider\VoxelionProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The Phase 0 provider decision swaps this single binding.
        $this->app->bind(VerificationProvider::class, VoxelionProvider::class);

        $this->app->bind(HandoffTokenService::class, fn () => new HandoffTokenService(
            (string) config('opinionpadi.handoff_public_key'),
            (string) config('opinionpadi.issuer'),
            (int) config('opinionpadi.handoff_max_age_seconds'),
        ));
    }

    public function boot(): void
    {
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
