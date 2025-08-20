<?php

/** @var \Laravel\Lumen\Routing\Router $router */

$router->get('/', function () use ($router) {
    return $router->app->version();
});

//$router->group(['middleware' => 'cors'], function () use ($router) {
    $router->get('/api/ip-info', 'IpInfoController@getIpInfo');
//});
