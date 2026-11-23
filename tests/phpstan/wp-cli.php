<?php

/**
 * Minimal WP-CLI declarations for static analysis (scanned, never executed).
 *
 * php-stubs/wp-cli-stubs requires wordpress-stubs < 7, which conflicts with
 * the installed WordPress 7 stubs, so only the methods used by the plugin
 * are declared here.
 */
class WP_CLI
{
    /**
     * @param  callable|object|class-string  $callable
     * @param  array<string, mixed>  $args
     */
    public static function add_command(string $name, mixed $callable, array $args = []): bool
    {
        return true;
    }

    public static function line(string $message = ''): void {}

    public static function log(string $message): void {}

    public static function success(string $message): void {}

    public static function warning(string $message): void {}

    /**
     * @return never
     */
    public static function error(string $message, bool|int $exit = true): void
    {
        exit(1);
    }
}
