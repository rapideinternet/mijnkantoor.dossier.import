<?php namespace Migration;

use Carbon\Carbon;
use Exception;
use Exceptions\CustomerNotFoundException;
use MijnKantoor\ApiClient;
use MijnKantoor\MappedDossierItem;

class DocumentMigrator
{
    public function __construct(
        protected $fileSystem,
        protected ApiClient $mkClient,
        protected $mutators = [],
        protected $customerWhitelist = [],
        protected $customerBlacklist = [],
        protected $deHammerCustomerDirBuffer = [],
        protected bool $dryRun = true
    )
    {

    }

    public function generateMappingTemplate($root, $customerDirPattern, $outputFile): bool
    {
        $uniqueFolders = [];

        if (file_exists($outputFile)) {
            $uniqueFolders = array_flip(file($outputFile, FILE_IGNORE_NEW_LINES));
        }

        try {
            foreach ($this->fileSystem->traverse($root) as $file) {
                preg_match($customerDirPattern, $file->relativePath, $matches);

                if (count($matches ?? []) < 3) {
                    echo "Warning: customer and relative path pattern not matched for file: " . $file . PHP_EOL;
                    continue;
                }

                $customerIdentifier = $matches['number'];
                $customerName = $matches['name'];
                $relativePath = $matches['relativePath'];

                if (!$customerIdentifier) {
                    echo "Warning: no customer dir found for file: " . $file->relativePath . PHP_EOL;
                    continue;
                }

                // if customer blacklist is set, skip all blacklisted customers
                if (in_array($customerIdentifier, $this->customerBlacklist)) {
                    echo "Skipping blacklisted customer: " . $customerIdentifier . PHP_EOL;
                    continue;
                }

                // match first number starting with 20 and exactly 4 digits
                // replace first occurrence of 20\d{2} with {year}
                // @todo, make dynamic
                $relativePath = preg_replace('/20\d{2}/', '{year}', $relativePath, 1);

                $path = trim($relativePath, '/');

                echo "path: '" . $path . "'" . PHP_EOL;

                if (!isset($uniqueFolders[$path])) {
                    var_dump("writing to file: " . $outputFile);
                    file_put_contents($outputFile, $path . PHP_EOL, FILE_APPEND);
                    $uniqueFolders[$path] = $file->relativePath;
                }
            }

        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }

        return true;
    }

    public function migrate(string | null $root = null): bool
    {
        $targetDirectories = $this->mkClient->allDirectoriesWithParentAndPath();

        $customers = $this->mkClient->allCustomerByNumber();

        foreach ($this->fileSystem->traverse($root) as $file) {

            echo "Processing file: " . $file . PHP_EOL;

            // create the target dossier item for MijnKantoor
            $dossierItem = new MappedDossierItem(
                filename: $file->filename,
            );

            // run the mutators to enrich the dossierItem object
            try {
                foreach ($this->mutators as $mutator) {
                    $dossierItem = $mutator->handle($file, $dossierItem);
                }
            } catch (CustomerNotFoundException) {
                echo "\tWarning: customer not found for file: " . $file->absolutePath . PHP_EOL;
                continue;
            }

            // if customer whitelist is set, skip all other customers
            if (count($this->customerWhitelist) && !in_array($dossierItem->customerNumber, $this->customerWhitelist)) {
                echo "\tSkipping non-whitelisted customer: " . $dossierItem->customerNumber . PHP_EOL;
                continue;
            }

            // if customer blacklist is set, skip all blacklisted customers
            if (count($this->customerBlacklist) && in_array($dossierItem->customerNumber, $this->customerBlacklist)) {
                echo "\tSkipping blacklisted customer: " . $dossierItem->customerNumber . PHP_EOL;
                continue;
            }

            // when destDir = '-' skip this file
            if ($dossierItem->destDir == '-') {
                echo "\tDir was explicitly set to skip: " . $file->relativePath . PHP_EOL;
                continue;
            }

            // target folder needs to be set by now
            if (!$dossierItem->destDir) {
                throw new Exception('Destination dir not set for file: ' . $file);
            }

            // translate the customer number to customer id
            $customerId = $customers[$dossierItem->customerNumber] ?? null;

            if (!$customerId) {
                echo "\tWarning: customer not found for number: " . $dossierItem->customerNumber . PHP_EOL;

                // @todo, move path to config file
                file_put_contents("unmappable_customers.log", $dossierItem->customerNumber . PHP_EOL, FILE_APPEND);
                continue;
            }

            $dossierItem->customerId = $customerId->id;

            // translate the destination directory to the id
            $dossierDirectory = $targetDirectories[strtolower($dossierItem->destDir)] ?? throw new Exception('Destination dir not found: ' . $dossierItem->destDir);
            $dossierItem->destDirId = $dossierDirectory->id;

            echo "\tUploading" . PHP_EOL;
            echo "\t\tdest customer: '" . $dossierItem->customerNumber . "'" . PHP_EOL;
            echo "\t\tdest dir: '" . $dossierItem->destDir . "'" . PHP_EOL;

            // when dry run is set, skip the actual upload
            if ($this->dryRun) {
                continue;
            }

            // get the file content
            $content = $this->fileSystem->getContent($file);

            if (strlen($content) == 0) {
                echo "\tWarning: empty file: " . $file->relativePath . PHP_EOL;
                continue;
            }

            // upload the file to MijnKantoor asynchronously
            $this->mkClient->uploadAsync([
                'resource' => $content,
                'customer_id' => $dossierItem->customerId,
                'dossier_directory_id' => $dossierItem->destDirId,
                'name' => $dossierItem->filename,
                'year' => $dossierItem->year,
                'period' => $dossierItem->period,
                'created_at' => $file->createdAt ? $file->createdAt->timestamp : null,
                'suppress_async' => '1', // prevents heavy directory calculations on the server
            ]);

            // when this is the first time this customer dir is encountered, do a little sleep to prevent hammering the server
            // when sharepoint is called to soon after first attempt, duplicate folders will be created
            if (!isset($this->deHammerCustomerDirBuffer[$dossierItem->customerId . $dossierItem->destDirId])) {
                echo "Sleeping for 3 seconds to prevent hammering the server" . PHP_EOL;
                $this->mkClient->finalizeUploads();
                sleep(3);
            }

            $this->deHammerCustomerDirBuffer[$dossierItem->customerId . $dossierItem->destDirId] = time();
        }

        // force the multi uploader to finalize even if the queue was not full enough to start
        $this->mkClient->finalizeUploads();

        return true;
    }

}