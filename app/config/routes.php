<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
/**
 * ------------------------------------------------------------------
 * LavaLust - an opensource lightweight PHP MVC Framework
 * ------------------------------------------------------------------
 *
 * MIT License
 *
 * Copyright (c) 2020 Ronald M. Marasigan
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package LavaLust
 * @author Ronald M. Marasigan <ronald.marasigan@yahoo.com>
 * @since Version 1
 * @link https://github.com/ronmarasigan/LavaLust
 * @license https://opensource.org/licenses/MIT MIT License
 */

/*
| -------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------
| Here is where you can register web routes for your application.
|
|
*/
/** @var object $router **/


// ---------------------------------------------------------------------------
// CAMPUSPHERE routes
//   public   : landing redirect, device API (token authenticated, CSRF-exempt)
//   guest    : login
//   secure   : everything else (signed in + role/path ACL, see SecureMiddleware)
// NOTE: static segments must be registered before '{id}' routes (first match wins).
// ---------------------------------------------------------------------------

$router->get('/', 'Home::index');
$router->post('/api/gate/scan', 'Gate_api::scan');

$router->group(['middleware' => ['guest']], function ($router) {
    $router->get('/login',  'Login::login');
    $router->post('/login', 'Login::login');
});

$router->group(['middleware' => ['secure']], function ($router) {
    $router->post('/logout', 'Login::logout');

    // --- account ---
    $router->get('/account',            'Account::index');
    $router->get('/account/password',   'Account::password');
    $router->post('/account/password',  'Account::password');

    // --- dashboards / portals ---
    $router->get('/dashboard', 'Dashboard::index');
    $router->get('/teacher',   'Teacher::index');
    $router->get('/student',          'Portal::index');
    $router->get('/student/grades',   'Portal::grades');
    $router->get('/student/requests', 'Portal::requests');
    $router->post('/student/requests', 'Portal::create_request');

    // --- students ---
    $router->get('/students',               'Students::index');
    $router->get('/students/export',        'Students::export');
    $router->get('/students/{id}',          'Students::show');
    $router->post('/students/{id}/update',  'Students::update');

    // --- enrollment ---
    $router->get('/enrollment',                 'Enrollment::index');
    $router->post('/enrollment',                'Enrollment::create');
    $router->post('/enrollment/{id}/verify',    'Enrollment::verify');
    $router->post('/enrollment/{id}/approve',   'Enrollment::approve');
    $router->post('/enrollment/{id}/reject',    'Enrollment::reject');

    // --- attendance ---
    $router->get('/attendance',                        'Attendance::index');
    $router->get('/attendance/class',                  'Attendance::class_sheet');
    $router->post('/attendance/class',                 'Attendance::save_class');
    $router->post('/attendance/record',                'Attendance::record');
    $router->post('/attendance/verify',                'Attendance::verify');
    $router->post('/attendance/notify',                'Attendance::notify');
    $router->post('/attendance/gate',                  'Attendance::gate_entry');
    $router->get('/attendance/devices',                'Attendance::devices');
    $router->post('/attendance/devices',               'Attendance::create_device');
    $router->post('/attendance/devices/{id}/toggle',   'Attendance::toggle_device');

    // --- academic records ---
    $router->get('/grades',        'Grades::index');
    $router->post('/grades/save',  'Grades::save');

    // --- activities ---
    $router->get('/activities',                          'Activities::index');
    $router->post('/activities',                         'Activities::create');
    $router->get('/activities/{id}',                     'Activities::show');
    $router->post('/activities/{id}/submit',             'Activities::submit');
    $router->post('/activities/{id}/grade',              'Activities::grade');
    $router->get('/activities/{id}/file/{student_id}',   'Activities::file');

    // --- student services ---
    $router->get('/services',                 'Services::index');
    $router->post('/services',                'Services::create');
    $router->post('/services/{id}/advance',   'Services::advance');

    // --- notifications ---
    $router->get('/notifications',               'Notifications::index');
    $router->post('/notifications/read-all',     'Notifications::read_all');
    $router->post('/notifications/announce',     'Notifications::announce');
    $router->post('/notifications/{id}/read',    'Notifications::read');

    // --- insights / standing / forecast / reports ---
    $router->get('/insights', 'Insights::index');

    $router->get('/guidance',                              'Guidance::index');
    $router->get('/guidance/{id}',                         'Guidance::show');
    $router->post('/guidance/{id}/note',                   'Guidance::note');
    $router->post('/guidance/{id}/intervention',           'Guidance::intervention');
    $router->post('/guidance/{id}/plan/{plan_id}/toggle',  'Guidance::toggle');

    $router->get('/forecast',        'Forecasts::index');
    $router->get('/forecast/export', 'Forecasts::export');

    $router->get('/reports',          'Reports::index');
    $router->get('/reports/{key}',    'Reports::download');

    // --- administration ---
    $router->get('/users',                'Users::index');
    $router->post('/users',               'Users::create');
    $router->post('/users/{id}/toggle',   'Users::toggle');
    $router->post('/users/{id}/reset',    'Users::reset');
    $router->get('/audit',                'Audit::index');
});
