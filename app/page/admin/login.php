<?php
/**
 * Hazra EV Admin — sign-in (Teresa design)
 *
 * $action, $csrf, $next, $logo, $siteName, $home
 */
$title    = $title ?? 'Sign in';
$page     = 'login';
$action   = $action ?? base_url('/api/v1/admin/login');
$next     = $next ?? base_url('/admin/dashboard');
$home     = $home ?? base_url('/');
$siteName = $siteName ?? 'Hazra EV';
$logo     = $logo ?? base_url('assets/hazraevLogo.jpg');
$csrf     = $csrf ?? Csrf::token();

App::render('admin/head', ['title' => $title, 'page' => $page]);
?>

<section class="adm-auth">
    <form class="adm-auth__card" id="adminLogin" method="post" action="<?= e($action) ?>" novalidate>
        <a class="adm-auth__brand" href="<?= e($home) ?>">
            <?php if ($logo !== ''): ?>
                <img src="<?= e($logo) ?>" alt="<?= e($siteName) ?>" width="48" height="48">
            <?php endif; ?>
            <span><?= e($siteName) ?></span>
        </a>

        <h1>Sign in</h1>
        <p class="adm-auth__lead">The control panel for this website. Ask an administrator if you do not have an account.</p>

        <input type="hidden" name="_token" value="<?= e($csrf) ?>">

        <label class="adm-field" for="admEmail">
            <span>Email</span>
            <input type="email" id="admEmail" name="email" autocomplete="username" required autofocus>
        </label>

        <label class="adm-field" for="admPassword">
            <span>Password</span>
            <input type="password" id="admPassword" name="password" autocomplete="current-password" required>
        </label>

        <label class="adm-check">
            <input type="checkbox" name="remember" value="1"> Keep me signed in on this device
        </label>

        <button type="submit" class="btn-primary adm-auth__submit">
            <i class="fa-solid fa-right-to-bracket"></i> Sign in
        </button>

        <p class="adm-auth__note" id="admNote" role="status" hidden></p>

        <a class="adm-auth__back" href="<?= e($home) ?>">
            <i class="fa-solid fa-arrow-left"></i> Back to the website
        </a>
    </form>
</section>

<script>
(function () {
    var form = document.getElementById('adminLogin');
    var note = document.getElementById('admNote');
    var button = form.querySelector('button[type="submit"]');
    var next = <?= json_encode($next, JSON_UNESCAPED_SLASHES) ?>;

    var say = function (message) {
        note.textContent = message;
        note.hidden = !message;
    };

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!form.reportValidity()) return;

        say('');
        button.disabled = true;

        var data = new FormData(form);

        fetch(form.action, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': data.get('_token') || ''
            },
            body: JSON.stringify({
                email: data.get('email') || '',
                password: data.get('password') || '',
                remember: data.get('remember') === '1'
            })
        })
        .then(function (res) {
            return res.json().catch(function () { return {}; })
                .then(function (body) { return { res: res, body: body }; });
        })
        .then(function (r) {
            if (r.res.ok) {
                window.location.assign(next);
                return;
            }
            var error = r.body.error || {};
            var fields = error.fields || {};
            var first = Object.keys(fields)[0];
            say(first ? fields[first] : (error.message || 'That did not work. Check the address and password.'));
            button.disabled = false;
        })
        .catch(function () {
            say('Could not reach the server.');
            button.disabled = false;
        });
    });
}());
</script>

</body>
</html>