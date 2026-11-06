<?php
// Run: php tests/admin-session-guard.php. No database, no network, no session files.
//
// Covers the two halves of admin session handling in app/controllers/Page.php:
// the reverse guard that keeps a signed-in admin off the sign-in form, and the
// no-store headers that are the only part able to defeat the back button.
//
// How the shimming works, and what it cannot prove:
//   * The app cannot boot here (no vendor/), so Page.php is read and evaluated
//     inside a throwaway namespace. The class body is byte-for-byte the real
//     file — only function *resolution* changes, so header(), headers_sent()
//     and base_url() land on recorders defined in that namespace while the
//     guard logic under test stays untouched.
//   * CLI silently drops real headers (headers_list() is always empty), hence
//     the recorder.
//   * `exit` is a language construct and cannot be shimmed, so each case runs
//     in its own child process: the guard really exits, and a shutdown handler
//     reports what was sent and whether the view was ever reached. That is the
//     assertion that matters — a redirect that did not stop the render would
//     show up as a recorded render.
//   * Not provable here: the literal "302" status line. Page.php sends a bare
//     `Location:` and PHP supplies 302 implicitly at the SAPI layer, which CLI
//     never reaches. The cases below assert the Location target and the aborted
//     render instead; the status code is PHP's own behaviour, not this code's.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

const PAGE_CONTROLLER = __DIR__ . '/../app/controllers/Page.php';

function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }

// --------------------------------------------------------------- the child
// One case per process. $argv[1] selects it; the result leaves as one JSON line.
function run_case_in_this_process(string $case): void
{
    $GLOBALS['shimHeaders'] = [];
    $GLOBALS['shimRendered'] = [];
    $GLOBALS['shimSessionStarts'] = 0;
    $GLOBALS['shimHeadersSent'] = ($case === 'dashboard-signed-in-headers-sent');
    $GLOBALS['shimError'] = null;

    register_shutdown_function(static function (): void {
        echo json_encode([
            'headers' => $GLOBALS['shimHeaders'],
            'rendered' => $GLOBALS['shimRendered'],
            'sessionStarts' => $GLOBALS['shimSessionStarts'],
            'error' => $GLOBALS['shimError'],
        ]);
    });

    // A warning (for example a double header() after output) must fail the case,
    // not pass unnoticed.
    set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    $signedIn = str_starts_with($case, 'dashboard-signed-in') || $case === 'login-signed-in';
    $_SESSION = $signedIn
        ? ['admin_logged_in' => true, 'user_role' => 'admin', 'user_id' => 'admin-1']
        : [];

    $shim = <<<'PHP'
        abstract class BaseController
        {
            protected function respond(string $view, array $data = [])
            {
                $GLOBALS['shimRendered'][] = $view;
                return 'rendered:' . $view;
            }
        }
        class Csrf
        {
            public static function ensureSession(): void { $GLOBALS['shimSessionStarts']++; }
        }
        function header(string $header, bool $replace = true, int $response_code = 0): void
        {
            if (\headers_sent()) { \trigger_error('header() after output', \E_USER_WARNING); return; }
            $GLOBALS['shimHeaders'][] = $header;
        }
        function headers_sent(&$filename = null, &$line = null): bool
        {
            return (bool) $GLOBALS['shimHeadersSent'];
        }
        function base_url($path = '')
        {
            $root = 'https://example.test';
            return $path === '' ? $root : $root . '/' . \ltrim((string) $path, '/');
        }
    PHP;

    // Only the opening tag goes; everything the guards do is evaluated verbatim.
    $source = preg_replace('/\A<\?php/', '', (string) file_get_contents(PAGE_CONTROLLER), 1);
    eval('namespace AdminGuardShim; ' . $shim . $source);

    try {
        $page = new AdminGuardShim\Page();
        if (str_starts_with($case, 'login-')) {
            $page->adminLogin();
        } elseif (str_starts_with($case, 'dashboard-')) {
            $page->adminDashboard();
        } else {
            throw new RuntimeException('Unknown case: ' . $case);
        }
    } catch (Throwable $error) {
        $GLOBALS['shimError'] = get_class($error) . ': ' . $error->getMessage();
    }
}

if (($argv[1] ?? '') !== '') {
    run_case_in_this_process($argv[1]);
    exit(0);
}

// -------------------------------------------------------------- the harness
function run_case(string $case): array
{
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' ' . escapeshellarg($case) . ' 2>&1';
    $output = [];
    $status = 0;
    exec($command, $output, $status);
    $raw = trim(implode("\n", $output));

    check($status === 0, "[$case] child exited with $status: $raw");
    $result = json_decode($raw, true);
    check(is_array($result), "[$case] child did not produce a clean JSON result: $raw");
    check($result['error'] === null, "[$case] child raised: " . (string) $result['error']);
    check($result['sessionStarts'] >= 1, "[$case] the guard did not ensure a session");

    return $result;
}

const NO_STORE = 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0';

function location(array $result): ?string
{
    foreach ($result['headers'] as $header) {
        if (str_starts_with($header, 'Location: ')) return substr($header, 10);
    }
    return null;
}

function assert_no_store(string $case, array $result): void
{
    check(in_array(NO_STORE, $result['headers'], true), "[$case] no-store Cache-Control was not sent: " . json_encode($result['headers']));
    check(in_array('Pragma: no-cache', $result['headers'], true), "[$case] Pragma: no-cache was not sent");
}

// 1. A signed-in admin asking for the sign-in form is sent to the dashboard.
$result = run_case('login-signed-in');
check(location($result) === 'https://example.test/admin/dashboard', '[login-signed-in] wrong redirect target: ' . var_export(location($result), true));
check($result['rendered'] === [], '[login-signed-in] the login form was still rendered: ' . json_encode($result['rendered']));
assert_no_store('login-signed-in', $result);

// 2. Anonymous gets the form, and the form is never cached — otherwise the back
//    button after signing in replays it.
$result = run_case('login-anonymous');
check($result['rendered'] === ['/app/page/admin/login.php'], '[login-anonymous] the login form did not render: ' . json_encode($result['rendered']));
check(location($result) === null, '[login-anonymous] an anonymous visitor was redirected away from the form');
assert_no_store('login-anonymous', $result);

// 3. Anonymous asking for the panel is sent to the sign-in form, and the view
//    behind the guard is never reached.
$result = run_case('dashboard-anonymous');
check(location($result) === 'https://example.test/admin/login', '[dashboard-anonymous] wrong redirect target: ' . var_export(location($result), true));
check($result['rendered'] === [], '[dashboard-anonymous] the dashboard rendered for an anonymous request: ' . json_encode($result['rendered']));
assert_no_store('dashboard-anonymous', $result);

// 4. A signed-in admin gets the panel, with no-store on it.
$result = run_case('dashboard-signed-in');
check($result['rendered'] === ['/app/page/admin/dashboard.php'], '[dashboard-signed-in] the dashboard did not render: ' . json_encode($result['rendered']));
check(location($result) === null, '[dashboard-signed-in] a signed-in admin was redirected');
assert_no_store('dashboard-signed-in', $result);

// 5. Calling the helper when output has already started is a no-op, not a
//    warning — that is what makes it safe to call from every guard.
$result = run_case('dashboard-signed-in-headers-sent');
check($result['headers'] === [], '[headers-sent] headers were sent after output began: ' . json_encode($result['headers']));
check($result['rendered'] === ['/app/page/admin/dashboard.php'], '[headers-sent] the dashboard did not render');

// ------------------------------------------- the two guards must stay in step
// A tripwire, not a style check: if one guard's idea of "signed in" is edited
// and the other is not, a signed-in admin can end up bounced between them.
$source = (string) file_get_contents(PAGE_CONTROLLER);
check(
    str_contains($source, "if (empty(\$_SESSION['admin_logged_in']) || (\$_SESSION['user_role'] ?? '') !== 'admin') {"),
    'guardAdmin() no longer uses the expected session-key test',
);
check(
    str_contains($source, "if (!empty(\$_SESSION['admin_logged_in']) && (\$_SESSION['user_role'] ?? '') === 'admin') {"),
    'guardGuest() is not the exact inverse of guardAdmin()\'s session-key test',
);
$guardAdminCalls = substr_count($source, '$this->guardAdmin();');
check($guardAdminCalls >= 22, "Expected every admin page to call guardAdmin(); found $guardAdminCalls call sites");
check(substr_count($source, '$this->noStore();') === 2, 'noStore() must be called from both guards and nowhere else');
check(substr_count($source, '$this->guardGuest();') === 1, 'guardGuest() must be called from adminLogin() only');

echo "Admin reverse guard, panel guard and no-store headers passed ({$guardAdminCalls} guardAdmin() call sites covered).\n";
