# Importer
This repository contains the code to migrate files from a remote filesystem to the current tenant in MijnKantoor.
It is designed to be used by developers and system integrators who need to migrate files from a remote filesystem to the current tenant in MijnKantoor.

## The procedure for migrating files from a remote filesystem to the current tenant
The procedure for migrating files from a remote filesystem to the current tenant is as follows:
1. Generate a mapping file that contains each unique path, ignoring the customer dir itself. So `FooCompany/bar` and `BarCompany/bar` are shared, but when FooCompany also has a dir called `baz` that BarCompany does not, the mapping file will contain `/bar` and `/baz`. The goals is that each unique relative path is only listed once, so that the customer can decide which destination dir to map the path to. 
2. Give the mapping file to the customer and ask them to fill in the destination directory for each path.
3. Receive the filled mapping file and check the content.
4. Run the migration script with the mapping file as input to actually migrate the files

## Step 1: generate a mapping file
### Step 1.1: Create case directory
To isolate each migration case and avoid conflicts, we create a dedicated directory for each case. 
This directory will contain all the necessary files and configurations for the migration. A typical 
case directory has this structure:

```
cases/                          # root of all cases (should be created manually)
└── mycustomer/                 # case directory (often the name of the customer)
    ├── .env                    # environment variables
    ├── generate_mapping.php    # script to generate directory mapping
    ├── mapping.csv             # output of the mapping script
    └── migrate.php             # script to run the migration (takes mapping.csv as input)
```    

#### Tip: Use a template.
You can copy a case directory that matches your needs and rename it to your case name. For
example when using sharepoint as a source filesystem, you can copy the `example_cases/sharepoint` directory 
and rename to the name of the customer or case you are working on, e.g. `cases/mycustomer`.

⚠️ Note: the `cases/` should be created manually in the root of the repository and is already in the .gignore file.
Never commit the `cases/` directory to the repository, as it contains customer-specific data.

### Step 1.2: configure source filesystem
In order to migrate files from a remote storage, we first need to configure the source filesystem adapter.
Currently, the following source filesystem adapters are supported:
- `local` - for local filesystems (this script needs to run on the same machine as the files Least favorable option).
- `sharepoint` - for SharePoint Online
- `mijnkantoor` - used when merging data from another tenant into the current tenant

Setup the source filesystem using values from the .env file in the case directory.
```php
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
    'site_id' => $_ENV['GRAPH_SITE_ID'], // see note below
    'debug' => true,
    'blacklist' => [],
]);

```
ℹ️ When using sharepoint you need to specify a site_id, but if you don't know it, you can use the
listSites() method, which is only available in the SharePoint adapter.
```php
var_dump($fileSystem->listSites());
die();
```

### Step 1.3: explore the source filesystem
Once the source filesystem is configured, you can generate the mapping file. To do so we first
need to explore the directory where that is the root for all the customer directories.
You can use the list() method on the file system adapter to get a list of all directories in the root 
and traverse even further down the directory structure.

```php
foreach ($fileSystem->list('/') as $item) {
    echo $item . "\n"; // list for example "Customer Dossier", "Supplier Documents", etc.
}

// and then to list the customers in the "Customer Dossier" directory
foreach ($fileSystem->list('/Customer Dossier') as $item) {
    echo $item . "\n";
}
die();
```

You can also use the traverse method to get a list of all directories in the root and traverse down the directory structure.
```php
foreach ($fileSystem->traverse('/') as $item) {
    echo $item . "\n";
}
```

### Step 1.4: configure the regex pattern
Once we establish the path structure the files live in that need to be migrated, we can construct a regular explression for it.

⚠️ Note that the regular expression is relative to the root of the customer directories, not the root of the filesystem.

So for example if the customer directories are structured like this:
```
12344.4A - De vries B.V./Some/Path/2030/Bar
```

We create a regular expression that captures the following parts:

* `number` - the customer number, e.g. `1234.2A`. This can also contain letters or a dot.
* `name` - the customer name, e.g. `My Customer`
* `relativePath` - the relative path to the customer directory, e.g. `tax/2025/foo.pdf`

To creat a regex, you can copy a path to a file from the traverse or list method and paste
it in regex101.com, and create a regex that returns the name, number and relative path.

🧠 Be careful when using LLMs to generate the regexes. Test it with multiple files in regex101.com
```php
$regexPattern = '#^(?<number>[^-]+)\s*-\s*(?<name>[^/]+)/(?<relativePath>.+)$#gm';
```

## Step 1.5: generate the mapping file
Whe then initialize the mapping generator using this regex pattern and the filesystem adapter we configured earlier.
```php
$generator = new MappingGenerator(
    fileSystem: $fileSystem,
);
```

Finally whe call the generateMappingTemplate method to generate the mapping file.
```php
$generator->generateMappingTemplate(
    root: "/path/to/client_root", // the folder the customers live in
    customerDirPattern: $regexPattern,
    outputFile: "mapping.csv"
);
```
⚠️ This wil create a mapping.csv in the case directory. When the script encounters a directory that is a year
it wil replace that with {year} in the mapping file.

⚠️ Note that most filesystem adapters support resume support. So when they are interrupted, they can resume from where they left off.
To do so they generate a processed_item.log inside the case directory. This file contains the list of items that have already been processed.
To start all over, you can delete this file. If you want to resume from where you left off, you can just run the script again.

## Step 2: give the mapping file to the customer
Once the mapping file is generated, you can give it to the customer. Before you can give the mapping file to the customer you first need to format it to this structure:
```
+-------------------+-----------------+
|    SourcePath     | DestinationPath |
+-------------------+-----------------+
| Tax/{year}/Report |                 |
| Tax/{year}/Scans  |                 |
+-------------------+-----------------+
```

The second column is empty, so the customer can fill in the destination path for each source path.

⚠️ The DestinationPath should be based on the case sensitive names in MijnKantoor seperated by a '/'. So example.

🧑‍🔧 Service: To prevent typo's, you can also provide a list of valid destination paths to the customer.
using `$mkClient->allDirectoriesWithParentAndPath()`
```
Permanent Dossier/Huurovereenkomsten
Belasting/{year}/Inkomstenbelasting
```

The `year` placeholder will be replaced with the actual year when the migration is run.

### Fallback dir
Customers may also specify a fallback directory for paths that do not match any of the specified destination paths.
You can later on configure this fallback directory in the migration script.

## Step 3: receive the filled mapping file
Once the customer has filled in the mapping file, you can receive it and check the content.
* Make sure that the file is in the correct format and that all destination paths are valid.
* Make sure the customer has specified a fallback directory for paths that do not match any of the specified destination paths.
* Convert the file to .csv if customer has provided it in .xlsx or .xls format.

## Step 4: run the migration script
Now we have the mapping file ready, we can run the migration script.

The migrator needs the vollowing components to run:
* A filesystem adapter to read the files from the source filesystem (same as in step 1.2)
* A MijnKantoor client to create the directories and files in the current tenant (see below)
* A list of mutators to process the files and directories during the migration
* An optional whitelist and blacklist of customer numbers to include or skip
* A dry run flag to simulate the migration without making changes

### 4.1 Set up the API client
First we set up the MijnKantoor client. This client is used to upload files to the destination tenant.
```php
$mkClient = new ApiClient([
    'access_token' => $_ENV['MK_ACCESS_TOKEN'],
    'tenant' => $_ENV['MK_TENANT'],
    'base_uri' => $_ENV['MK_BASE_URI'],
    'verify_ssl' => true, // set to false if you want to disable SSL verification (not recommended)
    'max_concurrency' => 3, // the client uses multi_curl to speed up requests
]); 
```

### 4.2 Setup the mutators
Then we configure the mutators that will process the files and directories during the migration.
Mutators are processed in a pipeline and each append some properties to the destination DossierItem based on the input file from the source filesystem.
For example a mutator can set the year on the destination DossierItem based on the source path, or set the destination directory based on a mapping file provided by the customer.
To see a full list of available mutators, see the `Mutators` directory.
```php
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
```

### 4.3 Construct the migrator
Finally, we can construct the migrator with all the components we configured earlier.

```php  
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
```

### 4.4 Run the migration
```php
$migrator->migrate("/"); // the root directory of the customer directories discovered in step 1.3
```
⚠️ Note, as the migrator uses the same filesystem adapter as the mapping generator, it will also use the processed_item.log file to resume from where it left off (which is the end).  So you want to delete this file.