<?php

class SetupCommand
{
    private string $baseDir;
    private array $framework;
    private bool $supportsColor;

    public function __construct(string $baseDir, array $framework)
    {
        $this->baseDir = $baseDir;
        $this->framework = $framework;
        $this->supportsColor = $this->detectColorSupport();
    }

    public function run(): void
    {
        $this->showBanner();
        $this->newline();

        if (!$this->runSystemChecks()) {
            $this->newline();
            $this->error('System requirements not met. Please fix the issues above and try again.');
            exit(1);
        }

        $this->newline();
        $this->info("Let's configure your project...");
        $this->newline();

        $config = $this->collectConfig();

        $this->newline();
        $this->info('Applying configuration...');
        $this->newline();

        $this->createEnvFile($config);
        $this->installDependencies();
        $this->setupGit($config);

        $this->newline();
        $this->showSuccessMessage();
    }

    // ── Banner ────────────────────────────────────────────

    private function showBanner(): void
    {
        $banner = <<<'ART'

         __      __
         \ \    / /__ _ _  _ _  _
          \ \  / / _` | || | | | |
           \_\/ /\__,_|\_, |___|_|
                       |__/
        ART;

        $this->line($this->color($banner, 'cyan'));
        $version = $this->framework['version'] ?? '1.0.0';
        $this->line($this->color("        {$this->framework['name']} v{$version}", 'dim'));
        $this->newline();
        $this->line($this->color('  Welcome to the Vayu setup wizard!', 'cyan'));
    }

    // ── System Checks ─────────────────────────────────────

    private function runSystemChecks(): bool
    {
        $this->info('Checking system requirements...');
        $this->newline();

        $passed = true;

        // PHP version
        $phpOk = version_compare(PHP_VERSION, '8.1.0', '>=');
        if ($phpOk) {
            $this->check('PHP >= 8.1 (found ' . PHP_VERSION . ')');
        } else {
            $this->fail('PHP >= 8.1 (found ' . PHP_VERSION . ')');
            $passed = false;
        }

        // Required extensions
        $required = ['pdo', 'mbstring', 'openssl', 'json'];
        foreach ($required as $ext) {
            if (extension_loaded($ext)) {
                $this->check("{$ext} extension");
            } else {
                $this->fail("{$ext} extension (required)");
                $passed = false;
            }
        }

        // Optional extensions
        $optional = [
            'curl'       => 'needed for Async::parallel()',
            'pdo_sqlite' => 'needed for SQLite database',
            'pdo_mysql'  => 'needed for MySQL database',
            'mongodb'    => 'needed for MongoDB database',
        ];
        foreach ($optional as $ext => $note) {
            if (extension_loaded($ext)) {
                $this->check("{$ext} extension");
            } else {
                $this->optional("{$ext} extension ({$note})");
            }
        }

        // Composer
        $composerInstalled = !empty(shell_exec('composer --version 2>/dev/null'));
        if ($composerInstalled) {
            $this->check('Composer');
        } else {
            $this->fail('Composer (required — install from https://getcomposer.org)');
            $passed = false;
        }

        return $passed;
    }

    private function check(string $text): void
    {
        $this->line('  ' . $this->color('  ✓ ', 'green') . $text);
    }

    private function fail(string $text): void
    {
        $this->line('  ' . $this->color('  ✗ ', 'red') . $text);
    }

    private function optional(string $text): void
    {
        $this->line('  ' . $this->color('  - ', 'yellow') . $this->color($text, 'dim'));
    }

    // ── Collect Config ────────────────────────────────────

    private function collectConfig(): array
    {
        $config = [];

        $config['APP_NAME'] = $this->ask('App Name', 'Vayu');

        $config['DB_TYPE'] = $this->askChoice('Database Type', [
            'sqlite'  => 'SQLite (file-based, no server needed)',
            'mysql'   => 'MySQL',
            'mongodb' => 'MongoDB',
        ], 'sqlite');

        if ($config['DB_TYPE'] === 'mysql') {
            $this->newline();
            $this->line($this->color('  MySQL Configuration:', 'dim'));
            $config['DB_HOST']     = $this->ask('  Database Host', '127.0.0.1');
            $config['DB_PORT']     = $this->ask('  Database Port', '3306');
            $config['DB_DATABASE'] = $this->ask('  Database Name', 'vayu');
            $config['DB_USERNAME'] = $this->ask('  Database Username', 'root');
            $config['DB_PASSWORD'] = $this->askSecret('  Database Password', '');
        } elseif ($config['DB_TYPE'] === 'mongodb') {
            $this->newline();
            $this->line($this->color('  MongoDB Configuration:', 'dim'));
            $config['DB_HOST']     = $this->ask('  Database Host', '127.0.0.1');
            $config['DB_PORT']     = $this->ask('  Database Port', '27017');
            $config['DB_DATABASE'] = $this->ask('  Database Name', 'vayu');
        }

        $this->newline();
        $config['init_git'] = $this->confirm('Initialize Git repository?', true);

        $config['git_remote'] = '';
        if ($config['init_git']) {
            if ($this->confirm('Add GitHub remote?', false)) {
                $config['git_remote'] = $this->ask('  Repository URL');
            }
        }

        return $config;
    }

    // ── Apply: .env ───────────────────────────────────────

    private function createEnvFile(array $config): void
    {
        $envPath = $this->baseDir . '/.env';
        $examplePath = $this->baseDir . '/.env.example';

        if (!file_exists($examplePath)) {
            $this->error('.env.example not found. Cannot create .env file.');
            return;
        }

        if (file_exists($envPath)) {
            if (!$this->confirm('.env already exists. Overwrite?', false)) {
                $this->warning('Skipped .env creation.');
                return;
            }
        }

        $template = file_get_contents($examplePath);

        $values = [
            'APP_NAME'    => $config['APP_NAME'],
            'DB_TYPE'     => $config['DB_TYPE'],
            'JWT_SECRET'  => $this->generateJwtSecret(),
        ];

        if ($config['DB_TYPE'] === 'mysql') {
            $values['DB_HOST']     = $config['DB_HOST'];
            $values['DB_PORT']     = $config['DB_PORT'];
            $values['DB_DATABASE'] = $config['DB_DATABASE'];
            $values['DB_USERNAME'] = $config['DB_USERNAME'];
            $values['DB_PASSWORD'] = $config['DB_PASSWORD'];
        } elseif ($config['DB_TYPE'] === 'mongodb') {
            $values['DB_HOST']     = $config['DB_HOST'];
            $values['DB_PORT']     = $config['DB_PORT'];
            $values['DB_DATABASE'] = $config['DB_DATABASE'];
        } elseif ($config['DB_TYPE'] === 'sqlite') {
            $values['DB_DATABASE'] = 'database/database.sqlite';
        }

        foreach ($values as $key => $value) {
            $template = preg_replace(
                '/^' . preg_quote($key, '/') . '=.*/m',
                $key . '=' . $value,
                $template
            );
        }

        file_put_contents($envPath, $template);

        if ($config['DB_TYPE'] === 'sqlite') {
            $sqliteDir = $this->baseDir . '/database';
            $sqlitePath = $sqliteDir . '/database.sqlite';
            if (!is_dir($sqliteDir)) {
                mkdir($sqliteDir, 0755, true);
            }
            if (!file_exists($sqlitePath)) {
                touch($sqlitePath);
            }
        }

        $this->success('Created .env file');
        $this->success('Generated JWT secret');
    }

    private function generateJwtSecret(): string
    {
        return bin2hex(random_bytes(32));
    }

    // ── Apply: Composer ───────────────────────────────────

    private function installDependencies(): void
    {
        if (is_dir($this->baseDir . '/vendor')) {
            $this->line('  ' . $this->color('  ✓ ', 'green') . 'Dependencies already installed');
            return;
        }

        $this->info('Installing dependencies...');
        $this->newline();

        $exitCode = 0;
        passthru('cd ' . escapeshellarg($this->baseDir) . ' && composer install 2>&1', $exitCode);

        $this->newline();
        if ($exitCode === 0) {
            $this->success('Dependencies installed');
        } else {
            $this->warning('Composer install failed. Run "composer install" manually.');
        }
    }

    // ── Apply: Git ────────────────────────────────────────

    private function setupGit(array $config): void
    {
        if (!$config['init_git']) {
            return;
        }

        if (is_dir($this->baseDir . '/.git')) {
            $this->line('  ' . $this->color('  ✓ ', 'green') . 'Git already initialized');
        } else {
            shell_exec('cd ' . escapeshellarg($this->baseDir) . ' && git init 2>&1');
            $this->success('Initialized Git repository');
        }

        if (!empty($config['git_remote'])) {
            $existing = trim(shell_exec('cd ' . escapeshellarg($this->baseDir) . ' && git remote get-url origin 2>/dev/null') ?? '');
            if ($existing) {
                $this->warning("Remote 'origin' already set to: {$existing}");
                if ($this->confirm('  Replace it?', false)) {
                    shell_exec('cd ' . escapeshellarg($this->baseDir) . ' && git remote set-url origin ' . escapeshellarg($config['git_remote']) . ' 2>&1');
                    $this->success('Updated remote origin');
                }
            } else {
                shell_exec('cd ' . escapeshellarg($this->baseDir) . ' && git remote add origin ' . escapeshellarg($config['git_remote']) . ' 2>&1');
                $this->success('Added remote origin: ' . $config['git_remote']);
            }
        }
    }

    // ── Success Message ───────────────────────────────────

    private function showSuccessMessage(): void
    {
        $line = str_repeat('─', 50);

        $this->line($this->color("  ┌{$line}┐", 'green'));
        $this->line($this->color('  │', 'green') . str_pad('', 14) . $this->color('Vayu is ready!', 'bold') . str_pad('', 22) . $this->color('│', 'green'));
        $this->line($this->color("  └{$line}┘", 'green'));
        $this->newline();
        $this->line($this->color('  Next steps:', 'bold'));
        $this->newline();
        $this->line('    1. Start your development server:');
        $this->line($this->color('       php -S localhost:8000', 'cyan'));
        $this->newline();
        $this->line('    2. Visit in your browser:');
        $this->line($this->color('       http://localhost:8000', 'cyan'));
        $this->newline();
        $this->line('    3. Edit your routes:');
        $this->line($this->color('       app/view.php', 'cyan') . '         (frontend pages)');
        $this->line($this->color('       api/gateway.php', 'cyan') . '      (API endpoints)');
        $this->newline();
        $this->line('    4. Read the docs:');
        $this->line($this->color('       docs/', 'cyan'));
        $this->newline();
        $this->line($this->color('  Happy coding! 🚀', 'dim'));
        $this->newline();
    }

    // ── Terminal Helpers ──────────────────────────────────

    private function detectColorSupport(): bool
    {
        if (getenv('NO_COLOR') !== false) {
            return false;
        }
        if (PHP_OS_FAMILY === 'Windows') {
            return getenv('ANSICON') !== false
                || getenv('ConEmuANSI') === 'ON'
                || str_contains(getenv('TERM') ?: '', 'xterm');
        }
        return function_exists('posix_isatty') ? posix_isatty(STDOUT) : true;
    }

    private function color(string $text, string $color): string
    {
        if (!$this->supportsColor) {
            return $text;
        }

        $codes = [
            'green'  => "\033[32m",
            'red'    => "\033[31m",
            'yellow' => "\033[33m",
            'cyan'   => "\033[36m",
            'bold'   => "\033[1m",
            'dim'    => "\033[2m",
        ];

        $code = $codes[$color] ?? '';
        return $code ? "{$code}{$text}\033[0m" : $text;
    }

    private function success(string $text): void
    {
        $this->line('  ' . $this->color('  ✓ ', 'green') . $text);
    }

    private function error(string $text): void
    {
        $this->line('  ' . $this->color('  ✗ ', 'red') . $text);
    }

    private function warning(string $text): void
    {
        $this->line('  ' . $this->color('  ! ', 'yellow') . $text);
    }

    private function info(string $text): void
    {
        $this->line('  ' . $this->color($text, 'cyan'));
    }

    private function line(string $text = ''): void
    {
        echo $text . PHP_EOL;
    }

    private function newline(): void
    {
        echo PHP_EOL;
    }

    // ── Prompt Helpers ────────────────────────────────────

    private function ask(string $question, string $default = ''): string
    {
        $hint = $default !== '' ? $this->color(" [{$default}]", 'dim') : '';
        $prompt = "  {$question}{$hint}: ";

        if (function_exists('readline')) {
            $input = readline($prompt);
        } else {
            echo $prompt;
            $input = fgets(STDIN);
        }

        $input = trim($input ?: '');
        return $input !== '' ? $input : $default;
    }

    private function askChoice(string $question, array $choices, string $default): string
    {
        $this->line("  {$question}:");

        $keys = array_keys($choices);
        $defaultIndex = array_search($default, $keys) + 1;

        foreach (array_values($choices) as $i => $description) {
            $num = $i + 1;
            $key = $keys[$i];
            $isDefault = $key === $default ? $this->color(' (default)', 'dim') : '';
            $this->line("    " . $this->color("[{$num}]", 'cyan') . " {$key} — {$description}{$isDefault}");
        }

        while (true) {
            $input = $this->ask('  Your choice', (string) $defaultIndex);
            $index = (int) $input - 1;

            if (isset($keys[$index])) {
                $this->newline();
                return $keys[$index];
            }

            if (in_array($input, $keys, true)) {
                $this->newline();
                return $input;
            }

            $this->warning("Invalid choice. Enter 1-" . count($keys) . ".");
        }
    }

    private function askSecret(string $question, string $default = ''): string
    {
        $hint = $default !== '' ? $this->color(" [{$default}]", 'dim') : '';
        $prompt = "  {$question}{$hint}: ";

        if (PHP_OS_FAMILY !== 'Windows' && function_exists('shell_exec')) {
            echo $prompt;
            shell_exec('stty -echo 2>/dev/null');
            $input = trim(fgets(STDIN) ?: '');
            shell_exec('stty echo 2>/dev/null');
            echo PHP_EOL;
        } else {
            $input = trim($this->ask($question, $default));
        }

        return $input !== '' ? $input : $default;
    }

    private function confirm(string $question, bool $default = true): bool
    {
        $hint = $default ? 'Y/n' : 'y/N';
        $input = $this->ask($question, $hint);

        if ($input === $hint) {
            return $default;
        }

        return in_array(strtolower($input), ['y', 'yes'], true);
    }
}
