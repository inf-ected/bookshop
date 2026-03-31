<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\PaymentProvider;
use App\Events\OrderPaid;
use App\Listeners\MergeGuestCartOnLogin;
use App\Listeners\SendOrderConfirmationEmail;
use App\Services\StripePaymentProvider;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\VKontakte\Provider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            PaymentProvider::class,
            StripePaymentProvider::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('vk', Provider::class);
            $event->extendSocialite('instagram', \SocialiteProviders\Instagram\Provider::class);
            $event->extendSocialite('facebook', \SocialiteProviders\Facebook\Provider::class);
        });

        Event::listen(Login::class, MergeGuestCartOnLogin::class);
        Event::listen(OrderPaid::class, SendOrderConfirmationEmail::class);
    }
}
