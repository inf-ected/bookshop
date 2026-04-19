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
     * @param  array<string, array{enabled: \Closure(): bool, factory: \Closure(): PaymentProvider}>  $definitions
     *                                                                                                              Each entry declares an `enabled` closure (checked at runtime against config) and a lazy
     *                                                                                                              `factory` closure (instantiated on first use to avoid boot-time credential failures).
     */
    public function __construct(private readonly array $definitions) {}

    /**
     * Slugs of all currently enabled providers.
     *
     * Only checks the `enabled` closure — does NOT instantiate providers.
     * Use this for rendering UI (e.g. cart checkout buttons) and for request validation,
     * where you need the list of valid slugs but not the provider objects themselves.
     *
     * @return list<string>
     */
    public function availableSlugs(): array
    {
        return array_values(array_filter(
            array_keys($this->definitions),
            fn (string $slug): bool => ($this->definitions[$slug]['enabled'])(),
        ));
    }

    /**
     * All currently enabled providers, keyed by slug.
     *
     * Instantiates each enabled provider on first call. Use only in paths that
     * actually need provider objects (e.g. after the checkout form is submitted).
     *
     * @return array<string, PaymentProvider>
     */
    public function available(): array
    {
        $result = [];

        foreach ($this->definitions as $slug => $definition) {
            if (($definition['enabled'])()) {
                $result[$slug] = $this->resolve($slug);
            }
        }

        return $result;
    }

    /**
     * Check whether a provider is registered AND currently enabled.
     *
     * Cheaper than calling available() when you only need to gate on a single slug
     * (e.g. in success() to validate the session-stored provider name).
     */
    public function isEnabled(string $slug): bool
    {
        return isset($this->definitions[$slug]) && ($this->definitions[$slug]['enabled'])();
    }

    /**
     * Resolve an enabled provider by its slug.
     *
     * @throws InvalidArgumentException if the provider is not registered or not enabled.
     */
    public function get(string $slug): PaymentProvider
    {
        if (! isset($this->definitions[$slug])) {
            throw new InvalidArgumentException("Payment provider '{$slug}' is not registered.");
        }

        if (! ($this->definitions[$slug]['enabled'])()) {
            throw new InvalidArgumentException("Payment provider '{$slug}' is not enabled.");
        }

        return $this->resolve($slug);
    }

    /**
     * Resolve a webhook-capable provider by its slug.
     *
     * Intentionally does NOT check the `enabled` flag — webhooks may arrive for a provider
     * that has since been disabled (e.g. in-flight payments). We process them to avoid losing
     * confirmed payments; the enabled flag only controls checkout UI availability.
     *
     * @throws InvalidArgumentException if the provider is not registered or does not support webhooks.
     */
    public function webhookProvider(string $slug): SupportsWebhooks
    {
        if (! isset($this->definitions[$slug])) {
            throw new InvalidArgumentException("Payment provider '{$slug}' is not registered.");
        }

        $provider = $this->resolve($slug);

        if (! $provider instanceof SupportsWebhooks) {
            throw new InvalidArgumentException("Payment provider '{$slug}' does not support webhooks.");
        }

        return $provider;
    }

    private function resolve(string $slug): PaymentProvider
    {
        if (! isset($this->resolved[$slug])) {
            $this->resolved[$slug] = ($this->definitions[$slug]['factory'])();
        }

        return $this->resolved[$slug];
    }
}
