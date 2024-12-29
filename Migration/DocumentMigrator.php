<?php namespace Migration;

use Exception;
use Exceptions\CustomerNotFoundException;
use MijnKantoor\ApiClient;
use MijnKantoor\DossierItem;
use MijnKantoor\MultiUploader;
use Storage\Directory;
use Storage\File;

class DocumentMigrator
{
    public function __construct(
        protected $fileSystem,
        protected ApiClient $mkClient,
        protected $mutators = [],
        protected $customerWhitelist = [],
        protected $customerBlacklist = [],
        protected $deHammerCustomerDirBuffer = []
    )
    {

    }

    public function generateMappingTemplate($root, $customerDirPattern, $outputFile): bool
    {
        $uniqueFolders = [];

        try {
            // @todo: fix resume support

            foreach ($this->fileSystem->traverse($root) as $file) {
                preg_match($customerDirPattern, $file->relativePath, $matches);

                if (count($matches) < 3) {
                    echo "Warning: customer and relative path pattern not matched for file: " . $file->filename . PHP_EOL;
                    continue;
                }

                $customerDir = $matches[1];
                $relativePath = $matches[2];

                if (!$customerDir) {
                    echo "Warning: no customer dir found for file: " . $file->relativePath . PHP_EOL;
                    continue;
                }

                // match first number starting with 20 and exactly 4 digits
                // replace first occurrence of 20\d{2} with {year}
                // @todo, make dynamic
                $relativePath = preg_replace('/20\d{2}/', '{year}', $relativePath, 1);

                $path = trim($relativePath, '/');

                echo "path: '" . $path . "'" . PHP_EOL;

                if (!isset($uniqueFolders[$path])) {
                    file_put_contents($outputFile, $path . PHP_EOL, FILE_APPEND);
                    $uniqueFolders[$path] = $file->relativePath;
                }
            }

        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }

        return true;
    }

    public function migrate(string $root = null): bool
    {
        $targetDirectories = $this->mkClient->allDirectoriesWithParentAndPath();
        $customers = $this->mkClient->allCustomerByNumber();

        foreach ($this->fileSystem->traverse($root) as $file) {

            // create the target dossier item for MijnKantoor
            $dossierItem = new DossierItem(
                filename: $file->filename,
            );

            // run the mutators to enrich the dossierItem object
            try {
                foreach ($this->mutators as $mutator) {
                    $dossierItem = $mutator->handle($file, $dossierItem);
                }
            } catch (CustomerNotFoundException) {
                echo "Warning: customer not found for file: " . $file->relativePath . PHP_EOL;
                continue;
            }

            // if customer whitelist is set, skip all other customers
            if (count($this->customerWhitelist) && !in_array($dossierItem->customerNumber, $this->customerWhitelist)) {
                echo "Skipping non-whitelisted customer: " . $dossierItem->customerNumber . PHP_EOL;
                continue;
            }

            // if customer blacklist is set, skip all blacklisted customers
            if (count($this->customerBlacklist) && in_array($dossierItem->customerNumber, $this->customerBlacklist)) {
                echo "Skipping blacklisted customer: " . $dossierItem->customerNumber . PHP_EOL;
                continue;
            }

            // target folder needs to be set by now
            if (!$dossierItem->destDir) {
                throw new Exception('Destination dir not set for file: ' . $file);
            }

            // translate the destination directory to the id
            $dossierDirectory = $targetDirectories[$dossierItem->destDir] ?? throw new Exception('Destination dir not found: ' . $dossierItem->destDir);
            $dossierItem->destDirId = $dossierDirectory->id;

            // translate the customer number to customer id
            $customerId = $customers[$dossierItem->customerNumber] ?? null;

            if (!$customerId) {
                echo "Warning: customer not found for number: " . $dossierItem->customerNumber . PHP_EOL;

                // @todo, move path to config file
                file_put_contents("unmappable_customers.log", $dossierItem->customerNumber . PHP_EOL, FILE_APPEND);
                continue;
            }

            $dossierItem->customerId = $customerId->id;

            // get the file content
            $content = $this->fileSystem->getContent($file);

            if (strlen($content) == 0) {
                echo "Warning: empty file: " . $file->relativePath . PHP_EOL;
                continue;
            }

            $this->mkClient->uploadAsync([
                'resource' => $content,
                'customer_id' => $dossierItem->customerId,
                'dossier_directory_id' => $dossierItem->destDirId,
                'name' => $dossierItem->filename,
                'year' => $dossierItem->year,
                'period' => $dossierItem->period,
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


//            $id = $this->mkClient->uploadDossierItem($dossierItem, $content);

//            if (!$id) {
//                echo "\t Failed to upload file: '" . $file->relativePath . "/" . $file->filename . "' to '" . $dossierItem->destDir . "' with id: " . $id . PHP_EOL;
//                break;
//            } else {
//                echo "\t Uploaded file: '" . $file->relativePath . "/" . $file->filename . "' to '" . $dossierItem->destDir . "' with id: " . $id . PHP_EOL;
//            }
        }

        // force the multi uploader to finalize even if the queue was not full enough to start
        $this->mkClient->finalizeUploads();

        return true;
    }

}