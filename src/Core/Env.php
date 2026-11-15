<?php

declare(strict_types=1);

namespace App\Core;

use Dotenv\Dotenv;

class Env
{
    private static bool $loaded = false;

    public static function load(string $directory): void
    {
        if (self::$loaded) {
            return;
        }

        if (class_exists(Dotenv::class)) {
            $dotenv = Dotenv::createImmutable($directory);
            $dotenv->safeLoad();
        }

        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }

        $val = getenv($key);
        if ($val !== false) {
            return $val;
        }

        return $default;
    }
}
