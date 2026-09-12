<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION = [];
session_destroy();

header('Location: ' . base_url('/admin/login'));
exit;
