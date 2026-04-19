<?php

declare(strict_types=1);

namespace App\Features\Checkout\Services;

use App\Features\Checkout\Contracts\PaymentProvider;
use App\Features\Checkout\Contracts\SupportsWebhooks;
use InvalidArgumentException;

class PaymentProviderRegistry
{
    /**
     * Resolved provider instances, keyed by slug.
     *
     * @var array<string, PaymentProvider>
     */
    private array $resolved = [];

    /**
     * @param  array<string, \Closure(): PaymentProvider>  $factories  Lazy factory closures keyed by slug.
     *                                                                 Providers are instantiated on first access so
     *                                                                 missing credentials for unused providers do not
     *                                                                 cause boot-time failures.
     */
    public function __construct(private readonly array $factories) {}

    /**
     * Resolve a provider by its slug.
     *
     * @throws InvalidArgumentException if the provider is not registered.
     */
    public function get(string $name): PaymentProvider
    {
        if (! $this->has($name)) {
            throw new InvalidArgumentException("Payment provider '{$name}' is not registered.");
        }

        if (! isset($this->resolved[$name])) {
            $this->resolved[$name] = ($this->factories[$name])();
        }

        return $this->resolved[$name];
    }

    /**
     * Check whether a provider slug is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->factories[$name]);
    }

    /**
     * Resolve a webhook-capable provider by its slug.
     *
     * @throws InvalidArgumentException if the provider is not registered or does not support webhooks.
     */
    public function webhookProvider(string $name): SupportsWebhooks
    {
        $provider = $this->get($name);

        if (! $provider instanceof SupportsWebhooks) {
            throw new InvalidArgumentException("Payment provider '{$name}' does not support webhooks.");
        }

        return $provider;
    }
}
