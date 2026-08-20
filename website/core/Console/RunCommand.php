<?php

class RunCommand
{
    private string $baseDir;
    private array $framework;

    public function __construct(string $baseDir, array $framework)
    {
        $this->baseDir = $baseDir;
        $this->framework = $framework;
    }

    public function run(array $args): void
    {
        $host = $this->parseOption($args, '--host', 'localhost');
        $port = $this->parseOption($args, '--port', '8000');

        if (!file_exists($this->baseDir . '/.env')) {
            echo PHP_EOL;
            echo "  \033[31m.env file not found.\033[0m Run \033[33mphp vayu setup\033[0m first." . PHP_EOL;
            echo PHP_EOL;
            exit(1);
        }

        $version = $this->framework['version'] ?? '1.0.0';

        echo PHP_EOL;
        echo "  \033[1m{$this->framework['name']}\033[0m v{$version}" . PHP_EOL;
        echo PHP_EOL;
        echo "  \033[32mServer running on:\033[0m \033[4mhttp://{$host}:{$port}\033[0m" . PHP_EOL;
        echo "  \033[2mPress Ctrl+C to stop\033[0m" . PHP_EOL;
        echo PHP_EOL;

        $serverFile = $this->baseDir . '/server.php';

        pcntl_exec(
            PHP_BINARY,
            ['-S', "{$host}:{$port}", $serverFile],
        ) || passthru(
            PHP_BINARY . ' -S ' . escapeshellarg("{$host}:{$port}") . ' ' . escapeshellarg($serverFile),
            $exitCode
        );
    }

    private function parseOption(array $args, string $flag, string $default): string
    {
        foreach ($args as $i => $arg) {
            if ($arg === $flag && isset($args[$i + 1])) {
                return $args[$i + 1];
            }
            if (str_starts_with($arg, "{$flag}=")) {
                return substr($arg, strlen($flag) + 1);
            }
        }
        return $default;
    }
}
