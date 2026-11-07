<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema; 

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(\App\Services\MembershipService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // GIỚI HẠN CHIỀU DÀI STRING MẶC ĐỊNH
        Schema::defaultStringLength(191);
        \Illuminate\Pagination\Paginator::defaultView('shared.pagination');
        \Illuminate\Pagination\Paginator::defaultSimpleView('shared.simple-pagination');
    }
}
