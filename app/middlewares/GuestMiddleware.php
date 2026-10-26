<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/** Redirect signed-in users away from guest-only pages (login). */
class GuestMiddleware
{
    public function handle(Closure $next)
    {
        $auth = lava_instance()->auth;
        if ($auth->check()) {
            redirect($auth->home());
            exit;
        }
        return $next();
    }
}
