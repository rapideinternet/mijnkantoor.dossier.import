<?php namespace Migration;

use Exception;
use MijnKantoor\ApiClient;
use MijnKantoor\DossierItem;
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
            foreach ($this->mutators as $mutator) {
                $dossierItem = $mutator->handle($file, $dossierItem);
            }

            var_dump($dossierItem);
            continue;

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
                continue;
            }

            $dossierItem->customerId = $customerId->id;

            // get the file content
            $content = $this->fileSystem->getContent($file);

            $id = $this->mkClient->uploadDossierItem($dossierItem, $content);

            if (!$id) {
                echo "\t Failed to upload file: '" . $file->relativePath . "/" . $file->filename . "' to '" . $dossierItem->destDir . "' with id: " . $id . PHP_EOL;
                break;
            } else {
                echo "\t Uploaded file: '" . $file->relativePath . "/" . $file->filename . "' to '" . $dossierItem->destDir . "' with id: " . $id . PHP_EOL;
            }
        }

        return true;
    }

}