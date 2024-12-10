<?php

require_once __DIR__ . '/vendor/autoload.php';

use Mapping\CsvMapper;
use Migration\DocumentMigrator;
use MijnKantoor\ApiClient;
use Mutators\CustomerNumberByRegex;
use Mutators\DestDirFromMapping;
use Mutators\YearFromFilename;
use Mutators\YearFromSourcePath;
use Storage\Local;
use Storage\SharePoint;

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$fileSystem = new SharePoint([
    'client_id' => $_ENV['GRAPH_CLIENT_ID'],
    'client_secret' => $_ENV['GRAPH_CLIENT_SECRET'],
    'site_id' => $_ENV['GRAPH_SITE_ID'],
    'token_url' => $_ENV['GRAPH_TOKEN_URL'],
//    'debug' => true,
    'blacklist' => [

    ],
]);

//$fileSystem = new Local([
//    'debug' => false,
//]);

// read mapping from csv file (first col is source, second col is dest), seperator is ';'
$mapping = (new CsvMapper('/Users/rensreinders/Desktop/groeneboe/mapping.csv'))->getMapping();

/*
 * Todo:
 * dir from filename?
 * period from source path
 * skip customer by number range
 * when year is mandatory, and no year is set defaultYear mutator
 * Centralize period detection regexes
 */

$mkClient = new ApiClient([
    'access_token' => $_ENV['MK_ACCESS_TOKEN'],
    'tenant' => $_ENV['MK_TENANT'],
    'base_uri' => $_ENV['MK_BASE_URI'],
]);

$mutators = [
    new CustomerNumberByRegex('/\/Documenten\/(\d+)\/.*/m'), // e.g. "{number} - {name}" of "/Dossier/{number}"
    new YearFromSourcePath(),
    new YearFromFilename(),
    new DestDirFromMapping($mapping),
];

$storage = new DocumentMigrator(
    fileSystem: $fileSystem,
    mkClient: $mkClient,
    mutators: $mutators,
    customerWhitelist: [
        '10316'
    ]
);

//$storage->generateMappingTemplate("/Documenten");

$storage->migrate("/Documenten");
