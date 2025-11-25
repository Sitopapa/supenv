<?php

namespace Sitopapa\Supenv\Tests;

use PHPUnit\Framework\TestCase;
use Sitopapa\Supenv\Supenv;

class SupenvTest extends TestCase
{
    private string $testDir;
    private string $envFile;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/supenv_test_' . uniqid();
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

    public function testLoadEmptyFileReturnsEmpty(): void
    {
        file_put_contents($this->envFile, '');
        $env = new Supenv($this->envFile);
        $env->load();
        
        $this->assertEmpty($env->getAll());
    }

    public function testLoadParsesKeyValuePairs(): void
    {
        file_put_contents($this->envFile, "APP_NAME=MyApp\nAPP_ENV=production");
        $env = new Supenv($this->envFile);
        $env->load();
        
        $this->assertEquals('MyApp', $env->get('APP_NAME'));
        $this->assertEquals('production', $env->get('APP_ENV'));
    }

    public function testLoadHandlesQuotedValues(): void
    {
        file_put_contents($this->envFile, 'APP_NAME="My Application"');
        $env = new Supenv($this->envFile);
        $env->load();
        
        $this->assertEquals('My Application', $env->get('APP_NAME'));
    }

    public function testLoadIgnoresComments(): void
    {
        file_put_contents($this->envFile, "# This is a comment\nAPP_NAME=MyApp");
        $env = new Supenv($this->envFile);
        $env->load();
        
        $this->assertEquals('MyApp', $env->get('APP_NAME'));
    }

    public function testLoadIgnoresEmptyLines(): void
    {
        file_put_contents($this->envFile, "APP_NAME=MyApp\n\nAPP_ENV=local");
        $env = new Supenv($this->envFile);
        $env->load();
        
        $this->assertCount(2, $env->getAll());
    }

    public function testGetReturnsDefaultWhenKeyNotFound(): void
    {
        file_put_contents($this->envFile, '');
        $env = new Supenv($this->envFile);
        $env->load();
        
        $this->assertEquals('default', $env->get('MISSING_KEY', 'default'));
    }

    public function testGetIntReturnsInteger(): void
    {
        file_put_contents($this->envFile, "DB_PORT=3306");
        $env = new Supenv($this->envFile);
        $env->load();
        
        $this->assertSame(3306, $env->getInt('DB_PORT'));
    }

    public function testGetIntReturnsDefaultWhenKeyNotFound(): void
    {
        file_put_contents($this->envFile, '');
        $env = new Supenv($this->envFile);
        $env->load();
        
        $this->assertSame(8080, $env->getInt('PORT', 8080));
    }

    public function testGetBoolReturnsBooleanForTrue(): void
    {
        $cases = ['true', 'TRUE', '1', 'yes', 'YES', 'on', 'ON'];
        
        foreach ($cases as $value) {
            file_put_contents($this->envFile, "APP_DEBUG=$value");
            $env = new Supenv($this->envFile);
            $env->load();
            
            $this->assertTrue($env->getBool('APP_DEBUG'), "Failed for value: $value");
        }
    }

    public function testGetBoolReturnsBooleanForFalse(): void
    {
        $cases = ['false', 'FALSE', '0', 'no', 'NO', 'off', 'OFF'];
        
        foreach ($cases as $value) {
            file_put_contents($this->envFile, "APP_DEBUG=$value");
            $env = new Supenv($this->envFile);
            $env->load();
            
            $this->assertFalse($env->getBool('APP_DEBUG'), "Failed for value: $value");
        }
    }

    public function testGetBoolReturnsDefaultWhenKeyNotFound(): void
    {
        file_put_contents($this->envFile, '');
        $env = new Supenv($this->envFile);
        $env->load();
        
        $this->assertTrue($env->getBool('DEBUG', true));
        $this->assertFalse($env->getBool('DEBUG', false));
    }

    public function testGetAllReturnsMaskedSensitiveData(): void
    {
        file_put_contents($this->envFile, "API_KEY=secret123\nDB_PASSWORD=pass123\nAPP_NAME=MyApp");
        $env = new Supenv($this->envFile);
        $env->load();
        
        $masked = $env->getAll(true);
        
        $this->assertEquals('********', $masked['API_KEY']);
        $this->assertEquals('********', $masked['DB_PASSWORD']);
        $this->assertEquals('MyApp', $masked['APP_NAME']);
    }

    public function testSetAddsNewKey(): void
    {
        file_put_contents($this->envFile, '');
        $env = new Supenv($this->envFile);
        $env->load();
        $env->set('NEW_KEY', 'new_value');
        
        $this->assertEquals('new_value', $env->get('NEW_KEY'));
    }

    public function testSetUpdatesExistingKey(): void
    {
        file_put_contents($this->envFile, 'APP_NAME=OldName');
        $env = new Supenv($this->envFile);
        $env->load();
        $env->set('APP_NAME', 'NewName');
        
        $this->assertEquals('NewName', $env->get('APP_NAME'));
    }

    public function testSetManyAddsMultipleKeys(): void
    {
        file_put_contents($this->envFile, '');
        $env = new Supenv($this->envFile);
        $env->load();
        $env->setMany([
            'KEY1' => 'value1',
            'KEY2' => 'value2',
            'KEY3' => 'value3'
        ]);
        
        $this->assertEquals('value1', $env->get('KEY1'));
        $this->assertEquals('value2', $env->get('KEY2'));
        $this->assertEquals('value3', $env->get('KEY3'));
    }

    public function testUnsetRemovesKey(): void
    {
        file_put_contents($this->envFile, "APP_NAME=MyApp\nAPP_ENV=local");
        $env = new Supenv($this->envFile);
        $env->load();
        $env->unset('APP_NAME');
        
        $this->assertNull($env->get('APP_NAME'));
        $this->assertEquals('local', $env->get('APP_ENV'));
    }

    public function testSaveCreatesBackup(): void
    {
        file_put_contents($this->envFile, 'ORIGINAL=value');
        $env = new Supenv($this->envFile);
        $env->load();
        $env->set('NEW', 'value');
        $env->save();
        
        $this->assertFileExists($this->envFile . '.bak');
        $this->assertEquals('ORIGINAL=value', file_get_contents($this->envFile . '.bak'));
    }

    public function testSavePreservesStructure(): void
    {
        $content = "# Comment\nAPP_NAME=MyApp\n\n# Another comment\nAPP_ENV=local";
        file_put_contents($this->envFile, $content);
        
        $env = new Supenv($this->envFile);
        $env->load();
        $env->set('APP_NAME', 'NewApp');
        $env->save();
        
        $saved = file_get_contents($this->envFile);
        $this->assertStringContainsString('# Comment', $saved);
        $this->assertStringContainsString('# Another comment', $saved);
        $this->assertStringContainsString('APP_NAME=NewApp', $saved);
    }

    public function testSaveQuotesValuesWithSpaces(): void
    {
        file_put_contents($this->envFile, '');
        $env = new Supenv($this->envFile);
        $env->load();
        $env->set('APP_NAME', 'My Application');
        $env->save();
        
        $saved = file_get_contents($this->envFile);
        $this->assertStringContainsString('APP_NAME="My Application"', $saved);
    }

    public function testRequireThrowsExceptionForMissingKeys(): void
    {
        file_put_contents($this->envFile, 'APP_NAME=MyApp');
        $env = new Supenv($this->envFile);
        $env->load();
        
        $this->expectException(\Sitopapa\Supenv\Exceptions\ValidationException::class);
        $this->expectExceptionMessage('Missing required env keys: DB_HOST, DB_PASSWORD');
        
        $env->require(['APP_NAME', 'DB_HOST', 'DB_PASSWORD']);
    }

    public function testRequirePassesWhenAllKeysExist(): void
    {
        file_put_contents($this->envFile, "APP_NAME=MyApp\nDB_HOST=localhost");
        $env = new Supenv($this->envFile);
        $env->load();
        
        $env->require(['APP_NAME', 'DB_HOST']);
        
        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    public function testCreateExampleGeneratesCorrectFormat(): void
    {
        file_put_contents($this->envFile, "# Config\nAPP_NAME=MyApp\nAPI_KEY=secret123");
        $exampleFile = $this->testDir . '/.env.example';
        
        $env = new Supenv($this->envFile);
        $env->load();
        $env->createExample($exampleFile);
        
        $content = file_get_contents($exampleFile);
        $this->assertStringContainsString('# Config', $content);
        $this->assertStringContainsString('APP_NAME=', $content);
        $this->assertStringContainsString('API_KEY=', $content);
        $this->assertStringNotContainsString('MyApp', $content);
        $this->assertStringNotContainsString('secret123', $content);
    }

    public function testToJsonConvertsToJson(): void
    {
        file_put_contents($this->envFile, "APP_NAME=MyApp\nAPP_ENV=local");
        $env = new Supenv($this->envFile);
        $env->load();
        
        $json = $env->toJson();
        $decoded = json_decode($json, true);
        
        $this->assertEquals('MyApp', $decoded['APP_NAME']);
        $this->assertEquals('local', $decoded['APP_ENV']);
    }

    public function testLoadNonExistentFileReturnsEmptyData(): void
    {
        $env = new Supenv($this->testDir . '/non-existent.env');
        $env->load();
        
        $this->assertEmpty($env->getAll());
    }
}
