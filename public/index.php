<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \Symfony\Component\Yaml\Yaml;
use \Twig\Environment;
use \Twig\Loader\FilesystemLoader;

require '../vendor/autoload.php';

$app = new \Slim\App([
    'settings' => [
        'displayErrorDetails' => true
    ]
]);

// twig (template engine)
$templatesFolder = __DIR__ .'/../templates';
$twig = new Environment(new FilesystemLoader($templatesFolder), []);

/*
 * load config
 */
$configFilepath = __DIR__ .'/../config.yml';
if (false == file_exists($configFilepath)) {
    throw new \Exception('Konfigurationdatei nicht gefunden: '. $configFilepath);
}
$config = Yaml::parseFile($configFilepath);

/*
 * database
 */
$db = new BVL\ListOfPlaces\Database();

/*
 * list of places
 */
$app->get('/', function (Request $request, Response $response, array $args) use ($twig, $config, $db) {
    $response->getBody()->write($twig->render('index.html.twig', [
        'url' => $config['url'],
        'places' => $db->getPlaces(),
    ]));

    return $response;
});

/*
 * place details
 */
$app->get('/place/', function (Request $request, Response $response, array $args) {
    $response->getBody()->write("Hello");

    return $response;
});

$app->run();
