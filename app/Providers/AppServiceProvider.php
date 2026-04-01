<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\PaymentProvider;
use App\Models\Book;
use App\Models\CartItem;
use App\Services\StripePaymentProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
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
        RateLimiter::for('download', function (Request $request) {
            $book = $request->route('book');
            $bookKey = $book instanceof Book ? $book->id : (string) $book;

            return Limit::perHour(10)->by('download:'.$request->user()?->id.':'.$bookKey);
        });

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
