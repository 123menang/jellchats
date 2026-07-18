<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];
    private array $globalMiddleware = [];

    public function addGlobalMiddleware(callable $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    public function get(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, is_callable($handler) && !is_array($handler) ? ['_closure', $handler] : $handler, $middleware);
    }

    public function post(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, is_callable($handler) && !is_array($handler) ? ['_closure', $handler] : $handler, $middleware);
    }

    public function any(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('GET|POST|OPTIONS', $path, is_callable($handler) && !is_array($handler) ? ['_closure', $handler] : $handler, $middleware);
    }

    private function addRoute(string $methods, string $path, array $handler, array $middleware): void
    {
        $this->routes[] = [
            'methods' => explode('|', $methods),
            'pattern' => $this->compilePattern($path),
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    private function compilePattern(string $path): string
    {
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path);
        // Allow catch-all {path} to match slashes
        $pattern = str_replace('(?P<path>[^/]+)', '(?P<path>.+)', $pattern);
        return '#^' . $pattern . '$#';
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $uri = $request->path();

        foreach ($this->routes as $route) {
            if (!in_array($method, $route['methods'], true)) {
                continue;
            }

            if (preg_match($route['pattern'], $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                $request->setParams($params);

                $middlewareChain = array_merge($this->globalMiddleware, $route['middleware']);

                $next = function (Request $req) use ($route) {
                    [$class, $method] = $route['handler'];
                    if ($class === '_closure') {
                        $method($req);
                    } else {
                        $controller = new $class();
                        $controller->$method($req);
                    }
                };

                foreach (array_reverse($middlewareChain) as $mw) {
                    $next = function (Request $req) use ($mw, $next) {
                        if (is_callable($mw)) {
                            $mw($req, $next);
                        } elseif (is_string($mw)) {
                            (new $mw())->handle($req, $next);
                        } elseif (is_object($mw) && method_exists($mw, 'handle')) {
                            $mw->handle($req, $next);
                        } else {
                            $next($req);
                        }
                    };
                }

                $next($request);
                return;
            }
        }

        Response::notFound();
    }
}
