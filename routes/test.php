<?php
if (defined('APP_TEST_ROUTES_LOADED')) return;
define('APP_TEST_ROUTES_LOADED', true);
if (\App\Core\Env::get('APP_ENV', 'production') === 'local') {
    \App\Core\Router::get('/test', 'TestController@index');
}
