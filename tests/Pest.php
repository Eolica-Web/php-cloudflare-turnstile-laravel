<?php

declare(strict_types=1);

use Eolica\LaravelCloudflareTurnstile\Tests\TestCase;

pest()->project()->github('Eolica-Web/php-cloudflare-turnstile');

pest()->extend(TestCase::class)->in(__DIR__);

/**
 * @template T of Throwable
 *
 * @param class-string<T> $class
 *
 * @return T|null
 */
function thrownBy(string $class, Closure $callback): ?Throwable
{
    try {
        $callback();
    } catch (Throwable $e) {
        if ($e instanceof $class) {
            return $e;
        }

        throw $e;
    }

    return null;
}
