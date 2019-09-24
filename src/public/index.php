<?php

require_once '../../vendor/autoload.php';

use Slim\Http\Request;
use Slim\Http\Response;
use Illuminate\Database\Capsule\Manager as Manager;
use Slim\App as App;

$db = new Manager();

$app = new App();



$app->get('/test', function(Request $request, Response $response, $args){
    $response->getBody()->write("Hello world");
    return $response;
});

$app->get('/selectChamp', function (Request $request, Response $response, $args){
    $response->getBody()->write('Select your fighters');
    return $response;
});



try {
    $app->run();
} catch (Throwable $e) {
    var_dump($e);
}


//$container=array();

//$container["settings"]=$config;

//$container["view"] = function($container){
//    $view = new \Slim\Views\Twig();
//};
