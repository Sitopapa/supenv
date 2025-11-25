<?php

namespace Sitopapa\Supenv\Exceptions;

/**
 * Thrown when encryption fails
 */
class EncryptionException extends SupenvException
{
    public function __construct(string $message = "Encryption failed", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
