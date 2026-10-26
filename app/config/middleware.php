<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
/*
|--------------------------------------------------------------------------
| Route middleware registry
|--------------------------------------------------------------------------
| guest  - only for visitors that are not signed in (login page)
| secure - signed-in user + role/path access map + forced password change
*/
require_once APP_DIR . 'middlewares/GuestMiddleware.php';
require_once APP_DIR . 'middlewares/SecureMiddleware.php';

$config['middlewares'] = [
    'guest'  => new GuestMiddleware(),
    'secure' => new SecureMiddleware(),
];
