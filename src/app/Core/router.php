<?php

namespace App\Core;

class Router
{
    private $routes;

    public function __construct(array $routes)
    {
        $this->routes = $routes;
    }

    public function dispatch($uri)
    {
        $path = parse_url($uri, PHP_URL_PATH);

        if (!isset($this->routes[$path])) {
            http_response_code(404);
            echo "404 Not Found";
            exit;
        }

        $handler = $this->routes[$path];
        list($controllerName, $method) = explode('@', $handler);

        $controllerClass = "App\\Controllers\\$controllerName";

        if (!class_exists($controllerClass)) {
            http_response_code(500);
            echo "Controller $controllerClass not found";
            exit;
        }

        $controller = new $controllerClass();

        if (!method_exists($controller, $method)) {
            http_response_code(500);
            echo "Method $method not found in controller $controllerClass";
            exit;
        }

        return $controller->$method();
    }
}
