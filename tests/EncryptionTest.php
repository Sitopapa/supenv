<?php

namespace Sitopapa\Supenv\Tests;

use PHPUnit\Framework\TestCase;
use Sitopapa\Supenv\Supenv;

class EncryptionTest extends TestCase
{
    private string $testDir;
    private string $envFile;
    private string $keyFile;
    private string $encFile;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/supenv_enc_test_' . uniqid();
        mkdir($this->testDir);
        $this->envFile = $this->testDir . '/.env';
        $this->keyFile = $this->testDir . '/.env.key';
        $this->encFile = $this->testDir . '/.env.enc';
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

    public function testEncryptCreatesKeyFileIfNotExists(): void
    {
        file_put_contents($this->envFile, 'APP_NAME=MyApp');
        
        $env = new Supenv($this->envFile);
        $env->encrypt($this->keyFile, $this->encFile);
        
        $this->assertFileExists($this->keyFile);
    }

    public function testEncryptCreatesEncryptedFile(): void
    {
        file_put_contents($this->envFile, 'APP_NAME=MyApp');
        
        $env = new Supenv($this->envFile);
        $env->encrypt($this->keyFile, $this->encFile);
        
        $this->assertFileExists($this->encFile);
    }

    public function testEncryptedContentIsDifferentFromOriginal(): void
    {
        $content = 'APP_NAME=MyApp\nAPI_KEY=secret123';
        file_put_contents($this->envFile, $content);
        
        $env = new Supenv($this->envFile);
        $env->encrypt($this->keyFile, $this->encFile);
        
        $encrypted = file_get_contents($this->encFile);
        $this->assertNotEquals($content, $encrypted);
        $this->assertStringNotContainsString('MyApp', $encrypted);
        $this->assertStringNotContainsString('secret123', $encrypted);
    }

    public function testDecryptRestoresOriginalContent(): void
    {
        $content = 'APP_NAME=MyApp\nAPI_KEY=secret123\nDB_PASSWORD=pass123';
        file_put_contents($this->envFile, $content);
        
        $env = new Supenv($this->envFile);
        $env->encrypt($this->keyFile, $this->encFile);
        
        // Delete original .env
        unlink($this->envFile);
        $this->assertFileDoesNotExist($this->envFile);
        
        // Decrypt
        $env->decrypt($this->encFile, $this->keyFile);
        
        // Verify
        $this->assertFileExists($this->envFile);
        $this->assertEquals($content, file_get_contents($this->envFile));
    }

    public function testDecryptThrowsExceptionIfEncryptedFileNotFound(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('File not found');
        
        $env = new Supenv($this->envFile);
        $env->decrypt($this->testDir . '/non-existent.enc', $this->keyFile);
    }

    public function testDecryptThrowsExceptionIfKeyFileNotFound(): void
    {
        file_put_contents($this->encFile, 'fake encrypted content');
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('File not found');
        
        $env = new Supenv($this->envFile);
        $env->decrypt($this->encFile, $this->testDir . '/non-existent.key');
    }

    public function testDecryptThrowsExceptionWithWrongKey(): void
    {
        $content = 'APP_NAME=MyApp';
        file_put_contents($this->envFile, $content);
        
        $env = new Supenv($this->envFile);
        $env->encrypt($this->keyFile, $this->encFile);
        
        // Create a different key
        $wrongKey = \sodium_crypto_secretbox_keygen();
        file_put_contents($this->keyFile, base64_encode($wrongKey));
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Decryption failed');
        
        $env->decrypt($this->encFile, $this->keyFile);
    }

    public function testEncryptUsesExistingKeyIfPresent(): void
    {
        // Create initial key
        $key = \sodium_crypto_secretbox_keygen();
        file_put_contents($this->keyFile, base64_encode($key));
        
        $content = 'APP_NAME=MyApp';
        file_put_contents($this->envFile, $content);
        
        $env = new Supenv($this->envFile);
        $env->encrypt($this->keyFile, $this->encFile);
        
        // Key file should not change
        $keyAfter = file_get_contents($this->keyFile);
        $this->assertEquals(base64_encode($key), $keyAfter);
    }

    public function testEncryptionIsReversible(): void
    {
        $testCases = [
            'Simple value',
            'Value with spaces',
            'Value with special chars: !@#$%^&*()',
            "Multiline\nValue\nWith\nNewlines",
            'Empty value:',
            'Very long value: ' . str_repeat('x', 1000),
        ];

        foreach ($testCases as $testCase) {
            file_put_contents($this->envFile, "TEST=$testCase");
            
            $env = new Supenv($this->envFile);
            $env->encrypt($this->keyFile, $this->encFile);
            
            unlink($this->envFile);
            $env->decrypt($this->encFile, $this->keyFile);
            
            $decrypted = file_get_contents($this->envFile);
            $this->assertEquals("TEST=$testCase", $decrypted, "Failed for: $testCase");
            
            // Clean up for next iteration
            unlink($this->encFile);
            unlink($this->keyFile);
        }
    }

    public function testEncryptReturnsSuccessMessage(): void
    {
        file_put_contents($this->envFile, 'APP_NAME=MyApp');
        
        $env = new Supenv($this->envFile);
        $result = $env->encrypt($this->keyFile, $this->encFile);
        
        $this->assertStringContainsString('Encrypted', $result);
        $this->assertStringContainsString($this->encFile, $result);
        $this->assertStringContainsString($this->keyFile, $result);
    }

    public function testMultipleEncryptionsWithSameKeyProduceDifferentCiphertext(): void
    {
        $content = 'APP_NAME=MyApp';
        file_put_contents($this->envFile, $content);
        
        $env = new Supenv($this->envFile);
        $env->encrypt($this->keyFile, $this->encFile . '.1');
        
        $encrypted1 = file_get_contents($this->encFile . '.1');
        
        // Encrypt again with same key
        $env->encrypt($this->keyFile, $this->encFile . '.2');
        $encrypted2 = file_get_contents($this->encFile . '.2');
        
        // Ciphertexts should be different (due to different nonces)
        $this->assertNotEquals($encrypted1, $encrypted2);
        
        // But both should decrypt to same content
        $env->decrypt($this->encFile . '.1', $this->keyFile);
        $decrypted1 = file_get_contents($this->envFile);
        
        $env->decrypt($this->encFile . '.2', $this->keyFile);
        $decrypted2 = file_get_contents($this->envFile);
        
        $this->assertEquals($content, $decrypted1);
        $this->assertEquals($content, $decrypted2);
    }
}
