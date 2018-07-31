<?php

namespace BVL\ListOfPlaces;

use Curl\Curl;
use Saft\Addition\HttpStore\Store\HttpStore;
use Saft\Rdf\CommonNamespaces;
use Saft\Rdf\NodeFactoryImpl;
use Saft\Rdf\RdfHelpers;
use Saft\Rdf\StatementFactoryImpl;
use Saft\Rdf\StatementIteratorFactoryImpl;
use Saft\Sparql\Result\ResultFactoryImpl;
use Symfony\Component\Cache\Simple\FilesystemCache;

class Database
{
    /**
     *
     */
    public function __construct()
    {
        // namespaces
        $this->commonNamespaces = new CommonNamespaces();
        $this->commonNamespaces->add(
            'pacc',
            'https://github.com/AKSW/leds-asp-f-ontologies/raw/master/ontologies/place-accessibility/ontology.ttl#'
        );
        $this->commonNamespaces->add('p', 'https://github.com/AKSW/leds-asp-f-ontologies/raw/master/ontologies/place/ontology.ttl#');
        $this->commonNamespaces->add('dbp', 'http://dbpedia.org/ontology/');
        $this->commonNamespaces->add('geo2', 'http://www.w3.org/2003/01/geo/');
        $this->commonNamespaces->add('wa', 'http://semweb.mmlab.be/ns/wa#');

        $this->rdfHelpers = new RdfHelpers();
        $this->nodeFactory = new NodeFactoryImpl();
        $this->statementFactory = new StatementFactoryImpl();
        $this->statementIteratorFactory = new StatementIteratorFactoryImpl();

        /*
         * this define call fixes the following errors:
         *
         * Notice: Use of undefined constant STDERR - assumed 'STDERR' in
         * ../vendor/php-curl-class/php-curl-class/src/Curl/Curl.php on line 1184
         *
         * Warning: curl_setopt(): supplied argument is not a valid File-Handle resource in
         * .../vendor/php-curl-class/php-curl-class/src/Curl/Curl.php on line 1000
         */
        define('STDERR', fopen('php://stderr', 'w'));
        $this->curl = new Curl();

        $this->store = new HttpStore(
            $this->nodeFactory,
            $this->statementFactory,
            new ResultFactoryImpl(),
            $this->statementIteratorFactory,
            $this->rdfHelpers,
            [
                'query-url' => 'https://opendata.leipzig.de/virt-sparql',
            ],
            $this->curl
        );

        $this->graphUri = 'https://opendata.leipzig.de/bvlplaces/';

        /*
         * cache
         */
        $this->cache = new FilesystemCache();

        // cache item is stored one day
        $this->timeToLive = 60*60*24;
    }

    /**
     * @param string $searchString
     * @param string $offset
     */
    public function getPlaces(string $searchString = null, int $offset = 0, int $limit = 40): array
    {
        $key = \hash('sha256', $searchString . $offset . $limit);

        if (!$this->cache->has($key)) {
            // get all place URIs
            $query = '
                PREFIX dc: <'.$this->commonNamespaces->getUri('dc11').'>
                PREFIX p: <'.$this->commonNamespaces->getUri('p').'>
                PREFIX rdf: <'.$this->commonNamespaces->getUri('rdf').'>
                SELECT ?s ?title
                  FROM <'.$this->graphUri.'>
                WHERE { ?s rdf:type p:Place';

            if (!empty($searchString)) {
                $query .= ' ; dc:title ?title
                    FILTER(REGEX(?title, "'.$searchString.'", "i")) }';
            } else {
                $query .= '. }';
            }

            $query .= ' OFFSET '.$offset.' LIMIT '.$limit;

            $entries = $this->store->query($query);

            $places = [];
            foreach ($entries as $entry) {
                $places[] = $this->getPlace($entry['s']->getUri());
            }

            // sort by title
            \uasort($places, function($a, $b) {
                return 0 < \strcasecmp($a['dc11:title'], $b['dc11:title']);
            });

            if (0 < \count($places)) {
                $this->cache->set($key, $places, $this->timeToLive);
            }
        }

        return $this->cache->get($key);
    }

    /**
     * @param string $placeURI
     */
    public function getPlace(string $placeURI): array
    {
        $key = hash('sha256', $placeURI);

        if (!$this->cache->has($key)) {
            // properties of the current place entry
            $properties = $this->store->query('
                PREFIX p: <'.$this->commonNamespaces->getUri('p').'>
                PREFIX rdf: <'.$this->commonNamespaces->getUri('rdf').'>
                SELECT ?p ?o
                WHERE { <'.$placeURI.'> ?p ?o. }
            ')->toArray();

            $place = [];
            foreach ($properties as $prop) {
                // type cast string to int, if available
                if (\ctype_digit($prop['o'])) {
                    $value = (float) $prop['o'];
                // type cast string to bool, if available
                } elseif ('true' === $prop['o'] || 'false' === $prop['o']) {
                    $value = 'true' == $prop['o'];
                } else {
                    $value = $prop['o'];
                }

                $place[$this->commonNamespaces->shortenUri($prop['p'])] = $value;
            }

            $this->cache->set($key, $place, $this->timeToLive);
        }

        return $this->cache->get($key);
    }

    /**
     *
     */
    public function getPlaceByID(string $id): array
    {
        $key = hash('sha256', $id);

        if (!$this->cache->has($key)) {
            $places = $this->store->query('
                PREFIX dc: <'.$this->commonNamespaces->getUri('dc').'>
                PREFIX p: <'.$this->commonNamespaces->getUri('p').'>
                PREFIX rdf: <'.$this->commonNamespaces->getUri('rdf').'>
                SELECT ?s
                WHERE {
                    ?s rdf:type p:Place ;
                       dc:identifier "'.$id.'" .
                }
            ');

            $place = $this->getPlace($places->toArray()[0]['s']);

            if (0 < \count($place)) {
                $this->cache->set($key, $place, $this->timeToLive);
            }
        }

        return $this->cache->get($key);
    }
}
