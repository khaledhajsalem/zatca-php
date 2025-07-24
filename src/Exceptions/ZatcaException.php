<?php

namespace KhaledHajSalem\Zatca\Exceptions;

use Exception;

/**
 * Base exception class for ZATCA package.
 */
class ZatcaException extends Exception
{
    protected array $context = [];

    public function __construct(string $message = '', array $context = [], int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function getContext(): array
    {
        return $this->context;
    }
} 