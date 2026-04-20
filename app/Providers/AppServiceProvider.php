<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\PaymentGateway;
use App\Features\Cart\Listeners\MergeGuestCartOnLogin;
use App\Features\Checkout\Events\OrderPaid;
use App\Features\Checkout\Listeners\SendOrderConfirmationEmail;
use App\Features\Checkout\Services\PaymentProviderRegistry;
use App\Features\Checkout\Services\PayPalPaymentProvider;
use App\Features\Checkout\Services\StripePaymentProvider;
use App\Features\Newsletter\Listeners\AddContactToNewsletter;
use App\Features\Pages\Observers\BookObserver;
use App\Features\Pages\Observers\PostObserver;
use App\Models\Book;
use App\Models\CartItem;
use App\Models\Post;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
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
        // Registry is the single source of truth for all payment provider resolution —
        // both checkout (available providers) and webhooks (by slug).
        // Each definition declares an `enabled` closure (checked at runtime against config)
        // and a lazy `factory` closure (instantiated on first access to avoid boot-time
        // credential failures for unconfigured providers).
        $this->app->singleton(PaymentProviderRegistry::class, function ($app): PaymentProviderRegistry {
            return new PaymentProviderRegistry([
                PaymentGateway::Stripe->value => [
                    'enabled' => fn (): bool => (bool) config('services.stripe.enabled', true),
                    'factory' => fn (): StripePaymentProvider => $app->make(StripePaymentProvider::class),
                ],
                PaymentGateway::PayPal->value => [
                    'enabled' => fn (): bool => (bool) config('services.paypal.enabled', false),
                    'factory' => fn (): PayPalPaymentProvider => $app->make(PayPalPaymentProvider::class),
                ],
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        URL::forceRootUrl(config('app.url'));
        if (str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Book::observe(BookObserver::class);
        Post::observe(PostObserver::class);

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('checkout', function (Request $request) {
            $userId = $request->user()?->id;

            return Limit::perMinute(5)->by($userId ?? $request->ip());
        });

        RateLimiter::for('download', function (Request $request) {
            $book = $request->route('book');
            $bookKey = $book instanceof Book ? $book->id : (string) $book;

            return Limit::perHour(10)->by('download:'.$request->user()?->id.':'.$bookKey);
        });

        Event::listen(Login::class, MergeGuestCartOnLogin::class);
        Event::listen(Registered::class, AddContactToNewsletter::class);
        Event::listen(OrderPaid::class, SendOrderConfirmationEmail::class);

        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('vk', Provider::class);
            $event->extendSocialite('instagram', \SocialiteProviders\Instagram\Provider::class);
            $event->extendSocialite('facebook', \SocialiteProviders\Facebook\Provider::class);
        });

        View::composer('partials.header', function (\Illuminate\View\View $view): void {
            if (! Schema::hasTable('cart_items')) {
                $view->with('cartCount', 0);

                return;
            }

            $query = CartItem::query();

            if (Auth::check()) {
                $query->where('user_id', Auth::id());
            } else {
                $query->whereNull('user_id')->where('session_id', session()->getId());
            }

            $view->with('cartCount', $query->count());
        });
    }
}
