<?php

namespace BVL\ListOfPlaces;

use Knorke\Importer;
use Knorke\ParserFactory;
use Knorke\ResourceGuyHelper;
use Saft\Addition\ARC2\Store\ARC2;
use Saft\Addition\ARC2\Store\NativeARC2Importer;
use Saft\Rdf\CommonNamespaces;
use Saft\Rdf\NodeFactoryImpl;
use Saft\Rdf\RdfHelpers;
use Saft\Rdf\StatementFactoryImpl;
use Saft\Rdf\StatementIteratorFactoryImpl;
use Saft\Sparql\Query\QueryFactoryImpl;
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

        $this->rdfHelpers = new RdfHelpers();
        $this->nodeFactory = new NodeFactoryImpl($this->rdfHelpers);
        $this->statementFactory = new StatementFactoryImpl();
        $this->statementIteratorFactory = new StatementIteratorFactoryImpl();
        $this->parserFactory = new ParserFactory(
            $this->nodeFactory,
            $this->statementFactory,
            $this->statementIteratorFactory,
            $this->rdfHelpers
        );

        $this->store = new ARC2(
            $this->nodeFactory,
            $this->statementFactory,
            new QueryFactoryImpl($this->rdfHelpers),
            new ResultFactoryImpl(),
            new StatementIteratorFactoryImpl(),
            $this->rdfHelpers,
            $this->commonNamespaces,
            [
                'username' => 'root',
                'password' => 'Pass123',
                'database' => 'places',
                'host' => 'db'
            ]
        );

        $this->importer = new NativeARC2Importer($this->store->getStore());

        $this->graphUri = 'https://behindertenverband-leipzig.de/ns/places/';

        $this->resourceGuyHelper = new ResourceGuyHelper(
            $this->store,
            [
                $this->graphUri
            ],
            $this->statementFactory,
            $this->nodeFactory,
            $this->rdfHelpers,
            $this->commonNamespaces
        );

        // cache
        $this->cache = new FilesystemCache();
    }

    /**
     *
     */
    public function getPlaces(int $offset = 0, int $limit = 10)
    {
        // get all place URIs
        $entries = $this->store->query('
            PREFIX p: <'.$this->commonNamespaces->getUri('p').'>
            PREFIX rdf: <'.$this->commonNamespaces->getUri('rdf').'>
            SELECT ?p
            WHERE { ?p rdf:type p:Place }
            OFFSET '. $offset .' LIMIT '.$limit.'
        ');

        $places = [];
        foreach ($entries as $entry) {
            $places[] = $this->resourceGuyHelper->createInstanceByUri($entry['p']->getUri(), 2);
        }

        return $places;
    }

    public function importFile(string $filepath)
    {
        $this->store->emptyAllTables();
        $file = new \SplFileObject($filepath);

        $batch = 200;
        $counter = 0;
        $chunk = '';

        // Loop until we reach the end of the file.
        while (!$file->eof()) {
            $chunk .= utf8_decode($file->fgets());

            if ($counter > $batch) {
                $this->store->query('INSERT INTO <'. $this->graphUri .'> {'. $chunk .'}');

                $chunk = '';
                $counter = 0;
            }

            ++$counter;
        }

        // if there is something not inserted yet
        if (0 < $counter) {
            $this->store->query('INSERT INTO <'. $this->graphUri .'> {'. $chunk .'}');
        }

        // Unset the file to call __destruct(), closing the file handle.
        $file = null;
    }
}
