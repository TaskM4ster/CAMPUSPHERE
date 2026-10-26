<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

if (!function_exists('icon')) {
    /** Inline SVG icon from the Lucide sprite at public/assets/icons/sprite.svg */
    function icon($name, $size = 16, $class = '')
    {
        return '<svg class="icon ' . e($class) . '" width="' . (int) $size . '" height="' . (int) $size
             . '" aria-hidden="true" focusable="false"><use href="' . e(base_url('assets/icons/sprite.svg')) . '#' . e($name) . '"/></svg>';
    }
}
