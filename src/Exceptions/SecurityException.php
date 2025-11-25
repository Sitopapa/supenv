<?php

namespace Sitopapa\Supenv\Exceptions;

/**
 * Thrown when a security issue is detected
 */
class SecurityException extends SupenvException
{
    public function __construct(string $message = "Security violation detected", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
