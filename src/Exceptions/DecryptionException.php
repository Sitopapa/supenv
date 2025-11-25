<?php

namespace Sitopapa\Supenv\Exceptions;

/**
 * Thrown when decryption fails
 */
class DecryptionException extends SupenvException
{
    public function __construct(string $message = "Decryption failed", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
