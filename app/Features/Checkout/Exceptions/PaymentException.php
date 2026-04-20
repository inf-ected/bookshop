<?php

declare(strict_types=1);

namespace App\Features\Checkout\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Generic payment exception thrown by any PaymentProvider implementation.
 * Wraps provider-specific exceptions (e.g. Stripe\Exception\ApiErrorException)
 * so calling code stays provider-agnostic.
 */
class PaymentException extends RuntimeException
{
    public static function fromThrowable(Throwable $e): self
    {
        return new self($e->getMessage(), $e->getCode(), $e);
    }
}
