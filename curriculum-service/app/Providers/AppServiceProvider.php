<?php

namespace App\Providers;

use App\Support\MasterData\LocalMasterDataProvider;
use App\Support\MasterData\MasterDataProvider;
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
        //
    }
}
