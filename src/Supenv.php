<?php

namespace Sitopapa\Supenv;

use Exception;
use RuntimeException;
use Sitopapa\Supenv\Exceptions\DecryptionException;
use Sitopapa\Supenv\Exceptions\FileNotFoundException;
use Sitopapa\Supenv\Exceptions\ValidationException;

class Supenv
{
    protected string $filePath;
    protected array $data = [];
    protected array $lines = [];

    public function __construct(string $filePath = '.env')
    {
        $this->filePath = $filePath;
    }

    public function load(): self
    {
        if (!file_exists($this->filePath)) return $this;

        $content = file_get_contents($this->filePath);
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $trimLine = trim($line);
            if (empty($trimLine) || str_starts_with($trimLine, '#')) {
                $this->lines[] = ['type' => 'raw', 'content' => $line];
                continue;
            }

            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, " \t\n\r\0\x0B\"'");
                $this->data[$key] = $value;
                $this->lines[] = ['type' => 'kv', 'key' => $key, 'value' => $value];
            } else {
                $this->lines[] = ['type' => 'raw', 'content' => $line];
            }
        }
        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function getInt(string $key, int $default = 0): int
    {
        return (int) ($this->data[$key] ?? $default);
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $val = strtolower($this->data[$key] ?? '');
        return in_array($val, ['true', '1', 'yes', 'on'], true) ?: $default;
    }

    public function getAll(bool $maskSensitive = false): array
    {
        if (!$maskSensitive) return $this->data;

        $masked = [];
        foreach ($this->data as $k => $v) {
            if (preg_match('/(KEY|SECRET|PASS|TOKEN)/i', $k)) {
                $masked[$k] = str_repeat('*', 8);
            } else {
                $masked[$k] = $v;
            }
        }
        return $masked;
    }

    public function toJson(): string
    {
        return json_encode($this->data, JSON_PRETTY_PRINT);
    }

    public function set(string $key, string $value, ?string $comment = null): self
    {
        $this->data[$key] = $value;
        $found = false;

        foreach ($this->lines as &$line) {
            if ($line['type'] === 'kv' && $line['key'] === $key) {
                $line['value'] = $value;
                $found = true;
                break;
            }
        }

        if (!$found) {
            if ($comment) {
                $this->lines[] = ['type' => 'raw', 'content' => "\n# $comment"];
            }
            $this->lines[] = ['type' => 'kv', 'key' => $key, 'value' => $value];
        }

        return $this;
    }

    public function setMany(array $values): self
    {
        foreach ($values as $key => $value) {
            $this->set($key, (string)$value);
        }
        return $this;
    }

    public function unset(string $key): self
    {
        if (!isset($this->data[$key])) return $this;

        unset($this->data[$key]);
        
        $this->lines = array_filter($this->lines, function ($line) use ($key) {
            return !($line['type'] === 'kv' && $line['key'] === $key);
        });

        return $this;
    }

    public function require(array $keys): void
    {
        $missing = [];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $this->data)) {
                $missing[] = $key;
            }
        }

        if (!empty($missing)) {
            throw new ValidationException($missing);
        }
    }

    public function save(): void
    {
        if (file_exists($this->filePath)) {
            copy($this->filePath, $this->filePath . '.bak');
        }

        $output = '';
        foreach ($this->lines as $line) {
            if ($line['type'] === 'raw') {
                $output .= $line['content'] . "\n";
            } else {
                $val = str_contains($line['value'], ' ') ? '"' . $line['value'] . '"' : $line['value'];
                $output .= "{$line['key']}={$val}\n";
            }
        }
        file_put_contents($this->filePath, rtrim($output));
    }

    public function createExample(string $targetPath = '.env.example'): void
    {
        $output = '';
        foreach ($this->lines as $line) {
            if ($line['type'] === 'raw') {
                $output .= $line['content'] . "\n";
            } else {
                $output .= "{$line['key']}=\n";
            }
        }
        file_put_contents($targetPath, rtrim($output));
    }

    public function encrypt(string $keyPath = '.env.key', ?string $outputFile = null): string
    {
        $outputFile = $outputFile ?? $this->filePath . '.enc';

        if (!file_exists($keyPath)) {
            $key = \sodium_crypto_secretbox_keygen();
            file_put_contents($keyPath, base64_encode($key));
        } else {
            $key = base64_decode(file_get_contents($keyPath));
        }

        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $content = file_get_contents($this->filePath);
        $ciphertext = \sodium_crypto_secretbox($content, $nonce, $key);

        file_put_contents($outputFile, base64_encode($nonce . $ciphertext));
        return "Encrypted to $outputFile using key in $keyPath";
    }

    public function decrypt(string $inputFile, string $keyPath = '.env.key'): void
    {
        if (!file_exists($inputFile)) {
            throw new FileNotFoundException($inputFile);
        }
        
        if (!file_exists($keyPath)) {
            throw new FileNotFoundException($keyPath);
        }

        $key = base64_decode(trim(file_get_contents($keyPath)));
        $decoded = base64_decode(file_get_contents($inputFile));

        $nonce = mb_substr($decoded, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
        $ciphertext = mb_substr($decoded, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');

        $plaintext = \sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
        if ($plaintext === false) {
            throw new DecryptionException("Decryption failed. Invalid key or corrupted data.");
        }

        file_put_contents($this->filePath, $plaintext);
    }
}