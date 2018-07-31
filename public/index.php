<?php

use \Symfony\Component\Yaml\Yaml;
use \Twig\Environment;
use \Twig\Loader\FilesystemLoader;
use \Symfony\Component\HttpFoundation\Request;

require '../vendor/autoload.php';

$db = new BVL\ListOfPlaces\Database();

// twig (template engine)
$templatesFolder = __DIR__ .'/../templates';
$twig = new Environment(new FilesystemLoader($templatesFolder), []);

/*
 * load config
 */
$configFilepath = __DIR__ .'/../config.yml';
if (false == \file_exists($configFilepath)) {
    throw new \Exception('Konfigurationdatei nicht gefunden: '. $configFilepath);
}
$config = Yaml::parseFile($configFilepath);

/*
 * request
 */
$request = Request::createFromGlobals();

/*
 * place list
 */
if (false === \strpos($request->getRequestUri(), 'place/')) {
    $places = $db->getPlaces($request->query->get('search'));

    echo $twig->render('index.html.twig', [
        'url' => $config['url'],
        'places' => $places,
        'search' => $request->query->get('search'),
    ]);

/*
 * Details view
 */
} else {
    // extract ID of the place from URL
    $id = substr($request->getRequestUri(), \strpos($request->getRequestUri(), 'place/')+6);

    echo $twig->render('place.html.twig', [
        'url' => $config['url'],
        'place' => $db->getPlaceByID($id),
    ]);
}
