<?php

class App
{
    public static function component(string $name, array $data = []): string
    {
        $path = __BASEDIR__ . "/app/components/" . $name . ".php";

        if (!file_exists($path)) {
            if (env('APP_ENV', 'production') === 'development') {
                return "<div style=\"color:red;border:1px solid red;padding:8px;margin:4px;font-size:12px;\">Component not found: <b>{$name}</b></div>";
            }
            error_log("[Vayu] Component '{$name}' not found at {$path}");
            return '';
        }

        ob_start();
        (static function ($__path, $__data) {
            extract($__data);
            include $__path;
        })($path, $data);

        return ob_get_clean();
    }

    public static function render(string $name, array $data = []): void
    {
        echo static::component($name, $data);
    }

    public static function exists(string $name): bool
    {
        return file_exists(__BASEDIR__ . "/app/components/" . $name . ".php");
    }
}
