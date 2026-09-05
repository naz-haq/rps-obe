<?php

namespace App\Providers;

use App\Support\MasterData\LocalMasterDataProvider;
use App\Support\MasterData\MasterDataProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Seam master data: Tahap 1 = lokal. Tahap 2 ganti binding ke
        // AcademicCoreMasterDataProvider tanpa mengubah model/logika OBE.
        $this->app->bind(MasterDataProvider::class, LocalMasterDataProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', fn(Request $request) => Limit::perMinute(5)
            ->by(strtolower(trim((string) $request->input('login'))) . '|' . $request->ip()));

        RateLimiter::for('authenticated-api', fn(Request $request) => Limit::perMinute(120)
            ->by((string) ($request->user()?->id ?? $request->ip())));

        RateLimiter::for('ai', fn(Request $request) => Limit::perMinute(12)
            ->by((string) ($request->user()?->institusi_id ?? $request->user()?->id ?? $request->ip())));

        RateLimiter::for('imports', fn(Request $request) => Limit::perMinute(3)
            ->by((string) ($request->user()?->institusi_id ?? $request->user()?->id ?? $request->ip())));
    }
}
