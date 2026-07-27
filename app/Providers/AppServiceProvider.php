<?php

namespace App\Providers;

use App\Mail\BrevoApiTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

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
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Brevo transactional email over HTTP. Laravel ships no brevo driver,
        // and outbound SMTP is often blocked on VPS hosts, so the API is the
        // dependable route. Select it with MAIL_MAILER=brevo.
        Mail::extend('brevo', function (array $config) {
            return new BrevoApiTransport(
                $config['key'] ?? config('services.brevo.key', ''),
                $config['endpoint'] ?? config('services.brevo.endpoint', 'https://api.brevo.com/v3/smtp/email'),
            );
        });
    }
}
