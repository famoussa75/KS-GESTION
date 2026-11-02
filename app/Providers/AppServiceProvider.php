<?php

namespace App\Providers;

use App\Models\GroupProfile;
use App\Models\Subsidiary;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        View::composer('*', function ($view) {
            if (! Schema::hasTable('group_profiles')) {
                return;
            }
            $view->with('ksGroup', GroupProfile::first());
            $view->with('ksSubsidiaries', Subsidiary::where('active', true)->orderBy('name')->get());
        });
    }
}
