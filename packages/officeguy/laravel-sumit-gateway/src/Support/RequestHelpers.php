<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Support;

/**
 * Request Helpers
 *
 * 1:1 port of OfficeGuyRequestHelpers.php
 * Provides utilities for accessing request data
 */
class RequestHelpers
{
    /**
     * Get a value from the request query parameters
     *
     * Port of: Get($Name)
     *
     * @param string $name Parameter name
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public static function get(string $name, mixed $default = null): mixed
    {
        return request()->query($name, $default);
    }

    /**
     * Get a value from the request POST data
     *
     * Port of: Post($Name)
     *
     * @param string $name Parameter name
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public static function post(string $name, mixed $default = null): mixed
    {
        return request()->input($name, $default);
    }

    /**
     * Get a value from either POST or GET data
     *
     * @param string $name Parameter name
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public static function input(string $name, mixed $default = null): mixed
    {
        return request()->input($name, $default);
    }

    /**
     * Check if a parameter exists in the request
     *
     * @param string $name Parameter name
     * @return bool
     */
    public static function has(string $name): bool
    {
        return request()->has($name);
    }

    /**
     * Get all request data
     *
     * @return array
     */
    public static function all(): array
    {
        return request()->all();
    }
}
