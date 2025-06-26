<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;


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
        Gate::define('admin-access', function ($user) {
            return $user instanceof \App\Models\Pegawai && $user->ID_JABATAN === 3;
        });

        Gate::define('owner-access', function ($user) {
            return $user instanceof \App\Models\Pegawai && $user->ID_JABATAN === 1;
        });

        Gate::define('cs-access', function ($user) {
            return $user instanceof \App\Models\Pegawai && $user->ID_JABATAN === 2;
        });

        Gate::define('gudang-access', function ($user) {
            return $user instanceof \App\Models\Pegawai && $user->ID_JABATAN === 4;
        });

        Gate::define('kurir-access', function ($user) {
            return $user instanceof \App\Models\Pegawai && $user->ID_JABATAN === 5;
        });

        Gate::define('hunter-access', function ($user) {
            return $user instanceof \App\Models\Pegawai && $user->ID_JABATAN === 6;
        });

        // ResetPassword::createUrlUsing(function ($notifiable, string $token) {
        //     return 'https://your-frontend-url.com/reset-password?token=' . $token . '&email=' . urlencode($notifiable->getEmailForPasswordReset());
        // });

        ResetPassword::toMailUsing(function ($notifiable, $token) {
            $url = url("/password/reset/{$token}");

            return (new MailMessage)
                ->subject('Reset Password Akun Anda')
                ->line('Kami menerima permintaan untuk mereset password Anda.')
                ->action('Reset Password', $url)
                ->line('Jika Anda tidak meminta reset ini, abaikan email ini.');
        });
    }
}
