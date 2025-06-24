<?php

ini_set('memory_limit', '1024M');

require_once __DIR__ . '/../../vendor/autoload.php';

use Migration\Migrator;
use MijnKantoor\ApiClient;
use Mutators\CustomerNumberAndRelativePathByRegex;
use Mutators\DestDirFromMapping;
use Mutators\PeriodFromSourcePath;
use Mutators\YearFromSourcePath;
use Dotenv\Dotenv;
use SourceFilesystem\SharePoint;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$mkClient = new ApiClient([
    'access_token' => $_ENV['MK_ACCESS_TOKEN'],
    'tenant' => $_ENV['MK_TENANT'],
    'base_uri' => $_ENV['MK_BASE_URI'],
    'verify_ssl' => true, // set to false if you want to disable SSL verification (not recommended)
    'max_concurrency' => 3, // the client uses multi_curl to speed up requests
]);

$fileSystem = new SharePoint([
    'client_id' => $_ENV['GRAPH_CLIENT_ID'],
    'client_secret' => $_ENV['GRAPH_CLIENT_SECRET'],
    'token_url' => $_ENV['GRAPH_TOKEN_URL'],
    'debug' => true,
    'blacklist' => [],
]);

$regexPattern = '#^(?<number>[^-]+)\s*-\s*(?<name>[^/]+)/(?<relativePath>.+)$#gm';

$mutators = [
    new CustomerNumberAndRelativePathByRegex($regexPattern), // should contain name, number and path
    new YearFromSourcePath(), // extracts the year from the source path
    new PeriodFromSourcePath(), // extracts the period from the source path
    new DestDirFromMapping(
        path: 'mapping_received_from_customer.csv', // the path to the mapping file the customer provided
        fallBackDir: "Migratie/Overige" // a fallback directory for unmapped items
    ),
];

$migrator = new Migrator(
    fileSystem: $fileSystem,
    mkClient: $mkClient,
    mutators: $mutators,
    customerWhitelist: [
        // a list of customer numbers to include
        // e.g. '12345', '67890'
    ],
    customerBlacklist: [
        // a list of customer numbers to skip
        // e.g. '11111', '22222'
    ],
    dryRun: false, // set to true to only simulate the migration without making changes
);

$migrator->migrate("/");