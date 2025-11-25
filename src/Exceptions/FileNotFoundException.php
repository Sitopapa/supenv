<?php

namespace Sitopapa\Supenv\Exceptions;

/**
 * Thrown when a file is not found
 */
class FileNotFoundException extends SupenvException
{
    public function __construct(string $filePath, int $code = 0, ?\Throwable $previous = null)
    {
        $message = "File not found: {$filePath}";
        parent::__construct($message, $code, $previous);
    }
}
