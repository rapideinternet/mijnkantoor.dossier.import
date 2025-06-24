<?php

ini_set('memory_limit', '1024M');

require_once __DIR__ . '/../../vendor/autoload.php';

use Migration\MappingGenerator;
use SourceFilesystem\SharePoint;
use Dotenv\Dotenv;

/*
 * Load the env from a .env file (should be in same location as this file)
 */
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$fileSystem = new SharePoint([
    'client_id' => $_ENV['GRAPH_CLIENT_ID'],
    'client_secret' => $_ENV['GRAPH_CLIENT_SECRET'],
    'token_url' => $_ENV['GRAPH_TOKEN_URL'],
    'site_id' => $_ENV['GRAPH_SITE_ID'], // if you don't know use listSites() (see below)
    'debug' => true,
    'blacklist' => [],
]);

/*
 * Tip: To list the available sites uncomment the following line
 */
// var_dump($fileSystem->listSites());
// die();

/*
 * Tip: Uncomment the following lines to list the contents of a path on the site.
 * Useful when determining the customer dossier root.
 */
//foreach ($fileSystem->list('/') as $item) {
//    echo $item . "\n";
//}
//die();

/*
 * Define the regex pattern that resolves the customerNumber, customerName and relativePath from the remote filesystem path
 */
$regexPattern = '#^(?<number>[^-]+)\s*-\s*(?<name>[^/]+)/(?<relativePath>.+)$#gm';

$generator = new MappingGenerator(
    fileSystem: $fileSystem,
);

$generator->generateMappingTemplate(
    root: "/path/to/client_root", // the folder the customers live in
    customerDirPattern: $regexPattern,
    outputFile: "mapping.csv"
);