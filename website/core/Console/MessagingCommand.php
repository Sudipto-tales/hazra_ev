<?php

/**
 * The console side of admin-managed messaging.
 *
 *   php vayu key:generate          print (or write) an APP_KEY
 *   php vayu webhooks:prune        drop inbound webhook events past retention
 *   php vayu otp:prune             wipe revealable OTP codes past their window
 *
 * key:generate prints by default and only touches .env when asked, because a
 * key that replaces an existing one makes every stored credential unreadable.
 * The other two are cron-shaped: idempotent, and safe to run as often as you
 * like.
 */
class MessagingCommand
{
    private string $baseDir;

    public function __construct(string $baseDir, array $framework = [])
    {
        $this->baseDir = $baseDir;
    }

    public function keyGenerate(array $args): void
    {
        require_once $this->baseDir . '/core/Secrets.php';

        $key = Secrets::generateKey();
        $write = in_array('--write', $args, true);
        $envPath = $this->baseDir . '/.env';

        echo PHP_EOL . "  \033[1mAPP_KEY\033[0m" . PHP_EOL . PHP_EOL;
        echo "  {$key}" . PHP_EOL . PHP_EOL;

        if (!$write) {
            echo "  Add it to this server's .env and to the PROD_ENV_FILE GitHub secret." . PHP_EOL;
            echo "  Pass \033[33m--write\033[0m to append it to .env here." . PHP_EOL . PHP_EOL;
            echo "  \033[31mKeep a copy.\033[0m Without this key every credential saved through" . PHP_EOL;
            echo "  the admin panel becomes unreadable, and they cannot be recovered." . PHP_EOL . PHP_EOL;
            return;
        }

        if (!file_exists($envPath)) {
            echo "  \033[31mNo .env at {$envPath}.\033[0m Create one first." . PHP_EOL . PHP_EOL;
            exit(1);
        }

        $env = (string) file_get_contents($envPath);
        if (preg_match('/^APP_KEY=.+$/m', $env)) {
            echo "  \033[31m.env already has an APP_KEY.\033[0m Replacing it would make every" . PHP_EOL;
            echo "  stored credential unreadable, so this command will not do it for you." . PHP_EOL . PHP_EOL;
            exit(1);
        }

        file_put_contents($envPath, rtrim($env, "\n") . "\n\nAPP_KEY={$key}\n");
        echo "  Appended to .env. Add the same value to the PROD_ENV_FILE secret before deploying." . PHP_EOL . PHP_EOL;
    }

    public function webhooksPrune(array $args): void
    {
        $this->boot();
        require_once $this->baseDir . '/core/messaging/WebhookEventLog.php';

        $days = $this->intOption($args, 'days');
        $removed = WebhookEventLog::prune($days);

        echo "  Removed {$removed} webhook event(s) older than "
            . ($days ?? MessagingRouter::setting('webhook_retention_days', '30')) . " day(s)." . PHP_EOL;
    }

    public function otpPrune(array $args): void
    {
        $this->boot();
        require_once $this->baseDir . '/core/OtpService.php';

        $wiped = OtpService::purgeCiphers();
        echo "  Wiped the stored code on {$wiped} challenge(s) past their reveal window." . PHP_EOL;
    }

    private function boot(): void
    {
        global $pdo;

        require_once $this->baseDir . '/config/db.php';
        require_once $this->baseDir . '/api/support/Uuid.php';

        if (!isset($pdo)) {
            echo "  \033[31mNo database connection.\033[0m" . PHP_EOL;
            exit(1);
        }
    }

    private function intOption(array $args, string $name): ?int
    {
        foreach ($args as $arg) {
            if (str_starts_with($arg, "--{$name}=")) {
                $value = substr($arg, strlen($name) + 3);

                return ctype_digit($value) ? (int) $value : null;
            }
        }

        return null;
    }
}
