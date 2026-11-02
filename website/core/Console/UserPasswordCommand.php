<?php

/**
 * Resets a user's password from the server console.
 *
 *   php vayu user:password --email=admin@hazraev.com
 *   php vayu user:password --email=someone@org.com --org=<org-id>
 *   php vayu user:password --email=admin@hazraev.com --no-must-change
 *
 * The shipped admin is created with a generated first-login credential (see
 * database/seeds/001_bootstrap_admin.php). If that printout is lost, or any
 * account needs a new password set by hand, this is the only safe way to do it:
 * there is no plaintext anywhere to recover.
 *
 * The new password is read interactively on stdin, twice, with terminal echo
 * disabled — it is never a CLI flag, so it never lands in shell history or the
 * process list. By default the account is flagged `must_change_password = 1`,
 * because a password somebody else typed is a first-login credential, not the
 * user's own; pass --no-must-change to set a password the user chose themselves.
 *
 * Emails are unique per organisation, not globally (users has
 * UNIQUE (org_id, email)), so if the same address exists in more than one org
 * the command stops and asks for --org rather than guessing.
 */
/** Raised when an email matches users in more than one organisation. */
class AmbiguousUserException extends RuntimeException
{
    /** @param list<array{id:string,org_id:string,name:string,role:string}> $matches */
    public function __construct(public array $matches)
    {
        parent::__construct('Email matches more than one organisation; pass --org.');
    }
}

class UserPasswordCommand
{
    private string $baseDir;
    private array $framework;

    public function __construct(string $baseDir, array $framework = [])
    {
        $this->baseDir = $baseDir;
        $this->framework = $framework;
    }

    public function run(array $args): void
    {
        global $pdo;

        require_once $this->baseDir . '/config/db.php';
        require_once $this->baseDir . '/api/support/Wire.php';
        require_once $this->baseDir . '/api/support/Envelope.php';
        require_once $this->baseDir . '/api/support/Password.php';

        if (!isset($pdo)) {
            $this->fail('No PDO connection. DB_TYPE must be sqlite or mysql.');
        }

        $email = $this->option($args, 'email');
        $org   = $this->option($args, 'org');
        $mustChange = !in_array('--no-must-change', $args, true);

        if ($email === null || $email === '') {
            $this->fail('Missing --email=<email>. Usage: php vayu user:password --email=<email> [--org=<id>] [--no-must-change]');
        }

        // Resolve the target before asking for a password — no point collecting a
        // credential for a user that does not exist, or one we cannot pin down.
        try {
            $user = self::resolve($email, $org);
        } catch (AmbiguousUserException $e) {
            echo PHP_EOL . "  \033[31m{$email} exists in more than one organisation.\033[0m Pass --org=<id>:" . PHP_EOL;
            foreach ($e->matches as $m) {
                echo "    \033[2m--org=\033[0m{$m['org_id']}  \033[2m({$m['name']}, {$m['role']})\033[0m" . PHP_EOL;
            }
            echo PHP_EOL;
            exit(1);
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        }

        echo PHP_EOL;
        echo "  \033[1muser:password\033[0m" . PHP_EOL;
        echo "  \033[2m{$user['name']} <{$email}> · {$user['role']} · org {$user['org_id']}\033[0m" . PHP_EOL . PHP_EOL;

        $password = $this->readNewPassword();

        self::apply($email, $user['org_id'], $password, $mustChange);

        echo PHP_EOL;
        echo "  \033[32mpassword updated\033[0m for {$email}" . PHP_EOL;
        echo $mustChange
            ? "  \033[2mmust_change_password = 1 — the app will require a new password on next login\033[0m" . PHP_EOL . PHP_EOL
            : "  \033[2mmust_change_password = 0 — treated as a password the user chose\033[0m" . PHP_EOL . PHP_EOL;
    }

    // ------------------------------------------------- resolve + update (pure)

    /**
     * Finds the single user for an email, honouring the per-org uniqueness of
     * the address. Throws RuntimeException when nothing matches and
     * AmbiguousUserException when the email spans orgs and none was named.
     *
     * No stdin, no echo, no connection bootstrap — it leans only on the db_*
     * helpers, so it is exercised directly by the test harness.
     *
     * @return array{id:string,org_id:string,name:string,role:string}
     */
    public static function resolve(string $email, ?string $org): array
    {
        $matches = $org !== null && $org !== ''
            ? db_fetch_all("SELECT id, org_id, name, role FROM users WHERE email = ? AND org_id = ?", [$email, $org])
            : db_fetch_all("SELECT id, org_id, name, role FROM users WHERE email = ?", [$email]);

        if (!$matches) {
            throw new RuntimeException(
                $org !== null && $org !== ''
                    ? "No user with email {$email} in org {$org}."
                    : "No user with email {$email}."
            );
        }

        if (count($matches) > 1) {
            throw new AmbiguousUserException($matches);
        }

        return $matches[0];
    }

    /**
     * Writes the new credential. Validates the password through the canonical
     * gate, hashes it with PASSWORD_BCRYPT (matching password_verify in the auth
     * controllers), and scopes the UPDATE to (email, org_id) so a per-org
     * duplicate is never touched by accident.
     */
    public static function apply(string $email, string $orgId, string $password, bool $mustChange): void
    {
        Password::validate($password);

        db_execute(
            "UPDATE users SET password_hash = ?, must_change_password = ?, updated_at = ? WHERE email = ? AND org_id = ?",
            [Password::hash($password), $mustChange ? 1 : 0, Wire::now(), $email, $orgId],
        );
    }

    // ------------------------------------------------------------- internals

    /**
     * Reads the new password twice on stdin with echo off, re-prompting until a
     * valid pair matches. Length is checked here with a friendly message before
     * the authoritative Password::validate() gate, so a too-short entry is a
     * re-prompt rather than an abort.
     */
    private function readNewPassword(): string
    {
        $interactive = $this->isTty();

        while (true) {
            $first  = $this->readSecret('  New password: ', $interactive);
            $length = strlen($first);

            if ($length < Password::MIN_LENGTH) {
                echo "  \033[31mToo short — at least " . Password::MIN_LENGTH . " characters.\033[0m" . PHP_EOL;
                continue;
            }
            if ($length > Password::MAX_LENGTH) {
                echo "  \033[31mToo long — " . Password::MAX_LENGTH . " characters or fewer.\033[0m" . PHP_EOL;
                continue;
            }

            $second = $this->readSecret('  Confirm password: ', $interactive);

            if (!hash_equals($first, $second)) {
                echo "  \033[31mPasswords did not match — try again.\033[0m" . PHP_EOL;
                continue;
            }

            return $first;
        }
    }

    /**
     * One line from stdin with terminal echo disabled where possible. `stty`
     * only applies to a real terminal, so a piped stdin (tests, CI) falls back
     * to a plain read. Echo is always restored, even if the read throws.
     */
    private function readSecret(string $prompt, bool $interactive): string
    {
        echo $prompt;

        $useStty = $interactive && $this->hasStty();

        if ($useStty) {
            shell_exec('stty -echo');
        }

        try {
            $line = fgets(STDIN);
        } finally {
            if ($useStty) {
                shell_exec('stty echo');
                echo PHP_EOL; // the Enter the user pressed was swallowed by -echo
            }
        }

        if ($line === false) {
            $this->fail('No input on stdin.');
        }

        return rtrim($line, "\r\n");
    }

    private function isTty(): bool
    {
        if (function_exists('stream_isatty')) {
            return @stream_isatty(STDIN);
        }
        if (function_exists('posix_isatty')) {
            return @posix_isatty(STDIN);
        }

        return false;
    }

    private function hasStty(): bool
    {
        if (!function_exists('shell_exec')) {
            return false;
        }

        $which = shell_exec('command -v stty 2>/dev/null');

        return is_string($which) && trim($which) !== '';
    }

    private function option(array $args, string $name): ?string
    {
        foreach ($args as $arg) {
            if (str_starts_with($arg, "--{$name}=")) {
                return substr($arg, strlen($name) + 3);
            }
        }

        return null;
    }

    private function fail(string $message): never
    {
        echo PHP_EOL . "  \033[31m{$message}\033[0m" . PHP_EOL . PHP_EOL;
        exit(1);
    }
}
