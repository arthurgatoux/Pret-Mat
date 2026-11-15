<?php

declare(strict_types=1);

namespace App\Core;

class Router
{
    /**
     * @var array<string, array<string, callable|array{0: class-string|object, 1: string}>>
     */
    private array $routes = [
        'GET' => [],
        'POST' => [],
    ];

    /**
     * @param callable|array{0: class-string|object, 1: string} $handler
     */
    public function get(string $path, callable|array $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    /**
     * @param callable|array{0: class-string|object, 1: string} $handler
     */
    public function post(string $path, callable|array $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->getMethod();
        $path = $request->getUri();

        // Si la requête est HEAD, on utilise les gestionnaires de routes GET
        $routeMethod = ($method === 'HEAD') ? 'GET' : $method;
        $handler = $this->routes[$routeMethod][$path] ?? null;

        if ($handler === null) {
            return Response::html(View::render('home/404', [
                'title' => 'Page non trouvée - PretMat'
            ]), 404);
        }

        if (is_callable($handler)) {
            $result = $handler($request);
        } elseif (is_array($handler) && count($handler) === 2) {
            [$controller, $action] = $handler;
            if (is_string($controller)) {
                $controller = new $controller();
            }
            $result = $controller->$action($request);
        } else {
            return Response::html('Erreur interne du serveur', 500);
        }

        if ($result instanceof Response) {
            return $result;
        }

        return Response::html((string) $result);
    }
}
