<?php
use Cake\Routing\RouteBuilder;
use Cake\Routing\Router;
use Cake\Routing\Route\DashedRoute;

Router::plugin(
    'OktaAuth',
    ['path' => '/okta-auth'],
    function (RouteBuilder $routes) {

        $routes->fallbacks(DashedRoute::class);
    }
);
