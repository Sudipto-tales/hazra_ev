<?php

/**
 * Loads the /api/v1 support layer.
 *
 * Required by RouteManager before it dispatches an api/ route, so the envelope
 * is available to the auth middleware as well as to the controllers — an
 * unauthenticated request has to fail in the contract shape too.
 *
 * Order matters only for V1Controller, which extends the framework's
 * ApiController and so must load after core/.
 */

require_once __DIR__ . '/Uuid.php';
require_once __DIR__ . '/Wire.php';
require_once __DIR__ . '/Geo.php';
require_once __DIR__ . '/Envelope.php';
require_once __DIR__ . '/Cursor.php';
require_once __DIR__ . '/Ctx.php';
require_once __DIR__ . '/Users.php';
require_once __DIR__ . '/Present.php';
require_once __DIR__ . '/Engine.php';

if (class_exists('ApiController')) {
    require_once __DIR__ . '/V1Controller.php';
}
