<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Env;
use ReflectionClass;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function createApplication(): Application
    {
        // Force APP_ENV=testing before the application bootstraps so that
        // CSRF middleware (VerifyCsrfToken::runningUnitTests) returns true
        // even when the Docker container's OS env has APP_ENV=local.
        putenv('APP_ENV=testing');
        $_ENV['APP_ENV'] = 'testing';
        $_SERVER['APP_ENV'] = 'testing';

        // PHPUnit's <env force="true"> tags write only to $_ENV, not to $_SERVER
        // or putenv(). phpdotenv's default adapters read $_SERVER FIRST
        // (ServerConstAdapter has higher priority than EnvConstAdapter). Inside
        // Docker, $_SERVER contains the container OS env (DB_CONNECTION=mysql),
        // which overwrites phpunit.xml values at config-load time, causing
        // RefreshDatabase to run migrate:fresh against the real MySQL database.
        //
        // Fix: propagate all phpunit.xml env values to both $_SERVER and putenv()
        // before resetting the Env repository, so every phpdotenv adapter reads
        // the correct test values.
        foreach ($_ENV as $key => $value) {
            if (is_string($value)) {
                $_SERVER[$key] = $value;
                putenv("{$key}={$value}");
            }
        }

        // Reset the cached Env repository via reflection so it re-reads
        // the forced env values on the next call to Env::getRepository().
        $reflection = new ReflectionClass(Env::class);
        $property = $reflection->getProperty('repository');
        $property->setValue(null, null);

        return parent::createApplication();
    }
}
