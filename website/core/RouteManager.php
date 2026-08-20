<?php

class RouteManager
{
    public static function dispatch(array $routes): void
    {
        $route = self::resolveRoute();

        if (str_starts_with($route, 'api/')) {
            self::dispatchApi($route, $routes);
            return;
        }

        if (isset($routes[$route])) {
            [$class, $method] = $routes[$route];

            if (!class_exists($class)) {
                http_response_code(500);
                die("Server Error: Controller not found ({$class})");
            }

            $controller = new $class();
            if (!method_exists($controller, $method)) {
                http_response_code(500);
                die("Server Error: Controller method not found ({$method})");
            }

            call_user_func([$controller, $method]);
            return;
        }

        http_response_code(404);
        load_view('resources/views/404.php');
    }

    private static function dispatchApi(string $route, array $routes): void
    {
        // The api/ tree ships its own support layer (envelope, context,
        // presenters). Load it before the middleware runs so an unauthenticated
        // request fails in the contract shape rather than the framework's.
        $apiSupport = __BASEDIR__ . '/api/support/bootstrap.php';
        if (file_exists($apiSupport)) {
            require_once $apiSupport;
        }

        Cors::handle();

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            return;
        }

        $method = HttpMethod::fromRequest();
        [$handler, $params] = self::matchApiRoute($method, $route, $routes);

        if (!$handler) {
            ApiResponse::notFound('API endpoint not found');
        }

        [$class, $action] = $handler;
        $middleware = $handler[2] ?? null;

        if ($middleware === 'auth') {
            $user = JwtAuth::authenticate();
            if (!$user) {
                if (class_exists('Envelope')) {
                    Envelope::unauthorized();
                }
                ApiResponse::unauthorized();
            }
        }

        if (!class_exists($class)) {
            ApiResponse::error("Controller not found: {$class}", 500);
        }

        $controller = new $class();

        if (!method_exists($controller, $action)) {
            ApiResponse::error("Method not found: {$action}", 500);
        }

        if (method_exists($controller, 'setRouteParams')) {
            $controller->setRouteParams($params);
        }

        $controller->{$action}();
    }

    private static function matchApiRoute(HttpMethod $method, string $route, array $routes): array
    {
        $key = $method->value . ':' . $route;

        if (isset($routes[$key])) {
            return [$routes[$key], []];
        }

        foreach ($routes as $pattern => $handler) {
            if (!str_starts_with($pattern, $method->value . ':')) {
                continue;
            }

            $pathPattern = substr($pattern, strlen($method->value) + 1);

            if (!str_contains($pathPattern, '{')) {
                continue;
            }

            $regex = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pathPattern);
            $regex = '#^' . $regex . '$#';

            if (preg_match($regex, $route, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                return [$handler, $params];
            }
        }

        return [null, []];
    }

    public static function resolveRoute(): string
    {
        if (!empty($_GET['route'])) {
            return trim($_GET['route'], '/');
        }

        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $scriptName = $_SERVER['SCRIPT_NAME'];
        $basePath = str_replace('\\', '/', dirname($scriptName));

        if ($basePath !== '/' && str_starts_with($requestUri, $basePath)) {
            $requestUri = substr($requestUri, strlen($basePath));
        }

        $route = trim($requestUri, '/');
        return $route === '' ? 'default' : $route;
    }
}