<?php

namespace Sitopapa\Supenv\Exceptions;

/**
 * Thrown when validation fails
 */
class ValidationException extends SupenvException
{
    protected array $missingKeys;

    public function __construct(array $missingKeys, int $code = 0, ?\Throwable $previous = null)
    {
        $this->missingKeys = $missingKeys;
        $message = "Missing required env keys: " . implode(', ', $missingKeys);
        parent::__construct($message, $code, $previous);
    }

    public function getMissingKeys(): array
    {
        return $this->missingKeys;
    }
}
