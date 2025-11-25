<?php

namespace Sitopapa\Supenv;

use Sitopapa\Supenv\Exceptions\SupenvException;

class Console
{
    public function handle(array $argv)
    {
        $command = $argv[1] ?? 'help';
        $args = array_slice($argv, 2);

        try {
            $supenv = new Supenv();
            
            match ($command) {
                'list' => $this->listCommand($supenv),
                'get' => $this->getCommand($supenv, $args),
                'set' => $this->setCommand($supenv, $args),
                'unset' => $this->unsetCommand($supenv, $args),
                'encrypt' => $this->encryptCommand($supenv, $args),
                'decrypt' => $this->decryptCommand($supenv, $args),
                'rotate' => $this->rotateCommand($supenv),
                'example' => $this->exampleCommand($supenv),
                'validate' => $this->validateCommand($supenv),
                default => $this->showHelp(),
            };
        } catch (SupenvException $e) {
            echo "\033[31mError:\033[0m " . $e->getMessage() . PHP_EOL;
            exit(1);
        } catch (\Exception $e) {
            echo "\033[31mError:\033[0m " . $e->getMessage() . PHP_EOL;
            exit(1);
        }
    }

    private function listCommand(Supenv $env)
    {
        $data = $env->load()->getAll(true);
        echo "\033[33m--- Environment Variables ---\033[0m" . PHP_EOL;
        foreach ($data as $key => $val) {
            echo "\033[32m$key\033[0m = $val" . PHP_EOL;
        }
    }

    private function setCommand(Supenv $env, array $args)
    {
        if (count($args) < 2) die("Usage: supenv set <KEY> <VALUE>\n");
        $env->load()->set($args[0], $args[1])->save();
        echo "✅ {$args[0]} set successfully.\n";
    }

    private function unsetCommand(Supenv $env, array $args)
    {
        if (empty($args[0])) die("Usage: supenv unset <KEY>\n");
        $env->load()->unset($args[0])->save();
        echo "🗑️  {$args[0]} removed.\n";
    }

    private function exampleCommand(Supenv $env)
    {
        $env->load()->createExample();
        echo "📄 .env.example created successfully.\n";
    }

    private function encryptCommand(Supenv $env, array $args)
    {
        $msg = $env->encrypt(); 
        echo "🔒 $msg\n";
    }

    private function decryptCommand(Supenv $env, array $args)
    {
        $input = $args[0] ?? '.env.enc';
        $env->decrypt($input);
        echo "🔓 Decrypted successfully to .env\n";
    }

    private function rotateCommand(Supenv $env)
    {
        $env->decrypt('.env.enc');
        unlink('.env.key');
        $env->load()->encrypt();
        echo "🔄 Key rotated successfully.\n";
    }

    private function getCommand(Supenv $env, array $args) {
        echo $env->load()->get($args[0] ?? '') . PHP_EOL;
    }

    private function validateCommand(Supenv $env) {
        try {
            $example = new Supenv('.env.example');
            $exampleData = $example->load()->getAll();
            $env->load()->require(array_keys($exampleData));
            echo "✅ Validation Passed!\n";
        } catch (\Exception $e) {
            echo "\033[31m❌ " . $e->getMessage() . "\033[0m\n";
            exit(1);
        }
    }

    private function showHelp()
    {
        echo <<<HELP
        \033[36mSupenv CLI (sitopapa/supenv)\033[0m
        Usage:
          list             Show all variables (masked)
          set <k> <v>      Set variable
          unset <k>        Delete variable
          get <k>          Get variable
          encrypt          Encrypt .env
          decrypt          Decrypt .env.enc
          example          Create .env.example from .env
          validate         Validate .env against .env.example
          rotate           Rotate encryption keys
        HELP;
    }
}