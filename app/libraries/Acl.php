<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Acl - role based access to URL path prefixes.
 *
 * Pure logic (no framework state) so it can be unit tested. The map itself lives
 * in app/config/campus.php (`access_map`). Row-level rules (e.g. "a teacher only
 * sees students of their own sections") are enforced in the models/controllers.
 */
class Acl
{
    /**
     * @param array  $map  role => [path prefixes]
     * @param string $role
     * @param string $path request path starting with "/"
     */
    public static function can(array $map, $role, $path)
    {
        if (!isset($map[$role])) {
            return false;               // unknown role => deny (fail closed)
        }
        $path = '/' . trim(strtok((string) $path, '?'), '/');
        foreach ($map[$role] as $prefix) {
            if ($path === $prefix || strpos($path, $prefix . '/') === 0) {
                return true;
            }
        }
        return false;
    }
}
