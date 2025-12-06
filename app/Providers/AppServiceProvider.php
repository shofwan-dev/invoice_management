<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Models\Setting;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        // Bagikan variabel settings ke semua view yang menggunakan layout 'app'
        View::composer('layouts.app', function ($view) {
            $settings = Setting::pluck('value', 'key')->toArray();
            $view->with('settings', $settings);
        });

        // Bagikan juga ke view settings Anda agar variabelnya terisi
        View::composer('settings.index', function ($view) {
            $settings = Setting::pluck('value', 'key')->toArray();
            $view->with('settings', $settings);
        });

        View::composer('layouts.guest', function ($view) {
            // Sesuaikan dengan cara Anda mengambil data settings
            $settings = Setting::pluck('value', 'key')->toArray(); 
            $view->with('settings', $settings);
        });
    }
}
