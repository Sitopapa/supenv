<?php

namespace Sitopapa\Supenv\Tests;

use PHPUnit\Framework\TestCase;
use Sitopapa\Supenv\Supenv;
use Sitopapa\Supenv\Exceptions\ValidationException;
use Sitopapa\Supenv\Exceptions\FileNotFoundException;
use Sitopapa\Supenv\Exceptions\DecryptionException;

class ExceptionTest extends TestCase
{
    private string $testDir;
    private string $envFile;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/supenv_exception_test_' . uniqid();
        mkdir($this->testDir);
        $this->envFile = $this->testDir . '/.env';
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testValidationExceptionStoresMissingKeys(): void
    {
        file_put_contents($this->envFile, 'APP_NAME=MyApp');
        $env = new Supenv($this->envFile);
        $env->load();

        try {
            $env->require(['APP_NAME', 'DB_HOST', 'API_KEY']);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $missing = $e->getMissingKeys();
            $this->assertCount(2, $missing);
            $this->assertContains('DB_HOST', $missing);
            $this->assertContains('API_KEY', $missing);
            $this->assertNotContains('APP_NAME', $missing);
        }
    }

    public function testValidationExceptionMessage(): void
    {
        file_put_contents($this->envFile, '');
        $env = new Supenv($this->envFile);
        $env->load();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Missing required env keys: KEY1, KEY2');
        
        $env->require(['KEY1', 'KEY2']);
    }

    public function testFileNotFoundExceptionForDecrypt(): void
    {
        $env = new Supenv($this->envFile);
        
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage('File not found');
        
        $env->decrypt($this->testDir . '/non-existent.enc');
    }

    public function testFileNotFoundExceptionForKey(): void
    {
        $encFile = $this->testDir . '/.env.enc';
        file_put_contents($encFile, 'fake'); // Create encrypted file
        
        $env = new Supenv($this->envFile);
        
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage('File not found');
        
        $env->decrypt($encFile, $this->testDir . '/non-existent.key');
    }

    public function testDecryptionExceptionWithWrongKey(): void
    {
        // Create and encrypt with one key
        file_put_contents($this->envFile, 'APP_NAME=MyApp');
        $keyFile = $this->testDir . '/.env.key';
        $encFile = $this->testDir . '/.env.enc';
        
        $env = new Supenv($this->envFile);
        $env->encrypt($keyFile, $encFile);
        
        // Replace with wrong key
        $wrongKey = \sodium_crypto_secretbox_keygen();
        file_put_contents($keyFile, base64_encode($wrongKey));
        
        $this->expectException(DecryptionException::class);
        $this->expectExceptionMessage('Decryption failed');
        
        $env->decrypt($encFile, $keyFile);
    }

    public function testDecryptionExceptionWithCorruptedData(): void
    {
        $keyFile = $this->testDir . '/.env.key';
        $encFile = $this->testDir . '/.env.enc';
        
        // Create key
        $key = \sodium_crypto_secretbox_keygen();
        file_put_contents($keyFile, base64_encode($key));
        
        // Create corrupted encrypted file (too short for nonce)
        file_put_contents($encFile, base64_encode(str_repeat('x', 5)));
        
        $env = new Supenv($this->envFile);
        
        // Either SodiumException or DecryptionException is acceptable
        try {
            $env->decrypt($encFile, $keyFile);
            $this->fail('Expected exception was not thrown');
        } catch (\SodiumException | DecryptionException $e) {
            $this->assertTrue(true);
        }
    }
}
