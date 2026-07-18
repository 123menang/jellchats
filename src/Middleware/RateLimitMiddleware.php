<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Services\RateLimiterService;

final class RateLimitMiddleware
{
    public function __construct(
        private string $key,
        private int $maxRequests = 60,
        private int $window = 60,
    ) {}

    public function handle(Request $request, callable $next): void
    {
        $limiter = \App\Core\App::rateLimiter();
        $limiter->require($this->key, $this->maxRequests, $this->window);
        $next($request);
    }
}
