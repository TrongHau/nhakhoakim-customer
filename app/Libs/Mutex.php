<?php

namespace App\Libs;

use Illuminate\Support\Facades\Cache;
use Closure;
use Exception;

class Mutex
{
    /**
     * Acquire lock (non-blocking)
     */
    public static function run(string $key, int $ttl, Closure $callback)
    {
        $lock = Cache::lock($key, $ttl);
        if (!$lock->get()) {
            throw new Exception("Process is locked: {$key}");
        }
        try {
            return $callback();
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Blocking lock (wait)
     */
    public static function block(string $key, int $ttl, int $wait, Closure $callback)
    {
        return Cache::lock($key, $ttl)->block($wait, function () use ($callback) {
            return $callback();
        });
    }

    /**
     * Manual acquire
     */
    public static function acquire(string $key, int $ttl = 60)
    {
        $lock = Cache::lock($key, $ttl);

        if ($lock->get()) {
            return $lock;
        }

        return null;
    }

    /**
     * Release manual lock
     */
    public static function release($lock)
    {
        if ($lock) {
            $lock->release();
        }
    }

    public static function hasCooldown(string $key): bool
    {
        try {
            $value = Cache::get($key);
            return $value !== null;
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function setCooldown(string $key, int $ttl): void
    {
        Cache::put($key, true, $ttl);
    }
}