<?php
namespace Sismil\Core;

use Exception;

class Router {
    private array $routes = [];

    /**
     * Adiciona uma rota.
     * @param string $method GET, POST, etc
     * @param string $path O caminho (ex: /sismil/backend/api/militar)
     * @param callable|array $handler A função ou [Controller::class, 'method']
     */
    public function addRoute(string $method, string $path, $handler) {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler
        ];
    }

    public function get(string $path, $handler) {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, $handler) {
        $this->addRoute('POST', $path, $handler);
    }

    public function dispatch(Request $request) {
        $method = $request->getMethod();
        $uri = $request->getUri();
        
        // Remove barras finais para padronizar
        $uri = rtrim($uri, '/');
        if (empty($uri)) $uri = '/';

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $route['path'] === $uri) {
                $handler = $route['handler'];

                if (is_array($handler)) {
                    // Instancia o Controller
                    $class = $handler[0];
                    $methodName = $handler[1];
                    
                    if (!class_exists($class)) {
                        Response::json(500, "Controller {$class} não encontrado.");
                    }
                    
                    $controller = new $class();
                    if (!method_exists($controller, $methodName)) {
                        Response::json(500, "Método {$methodName} não encontrado no Controller {$class}.");
                    }
                    
                    return call_user_func([$controller, $methodName], $request);
                } else if (is_callable($handler)) {
                    return call_user_func($handler, $request);
                }
            }
        }

        Response::json(404, "Endpoint não encontrado: {$method} {$uri}");
    }
}
