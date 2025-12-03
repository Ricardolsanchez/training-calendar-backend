<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Mail;
use App\Mail\Transport\BrevoTransport;

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
        Mail::extend('brevo', function () {
            return new BrevoTransport(env('BREVO_API_KEY'));
        });
    }
}
