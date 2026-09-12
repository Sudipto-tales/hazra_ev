<?php
$title = 'Sign In';
$page = 'login';
$action = base_url('/api/v1/admin/login');
$next = base_url('/admin/dashboard');
$home = base_url('/');
$siteName = 'Hazra EV Admin';
$csrf = Csrf::token();

App::render('admin/head', ['title' => $title, 'page' => $page]);
?>
<section class="adm-auth">
    <form class="adm-auth__card" id="adminLogin" method="post" action="<?= e($action) ?>" novalidate>
        <a class="adm-auth__brand" href="<?= e($home) ?>">
            <span>⚡ <?= e($siteName) ?></span>
        </a>
        <h1>Sign in</h1>
        <p class="adm-auth__lead">The control panel for Hazra EV. Enter your credentials to manage your store.</p>
        <input type="hidden" name="_token" value="<?= e($csrf) ?>">
        <label class="adm-field" for="admEmail">
            <span>Email</span>
            <input type="email" id="admEmail" name="email" autocomplete="username" required autofocus>
        </label>
        <label class="adm-field" for="admPassword">
            <span>Password</span>
            <input type="password" id="admPassword" name="password" autocomplete="current-password" required>
        </label>
        <button type="submit" class="btn-primary adm-auth__submit">
            <i class="fa-solid fa-right-to-bracket"></i> Sign in
        </button>
        <p class="adm-auth__note" id="admNote" role="status" hidden></p>
        <a class="adm-auth__back" href="<?= e($home) ?>"><i class="fa-solid fa-arrow-left"></i> Back to website</a>
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
                    password: data.get('password') || ''
                })
            })
            .then(function (res) { return res.json().then(function (body) { return { res: res, body: body }; }); })
            .then(function (r) {
                if (r.res.ok) {
                    window.location.assign(next);
                    return;
                }
                var error = r.body.error || {};
                say(error.message || 'Invalid email or password');
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
