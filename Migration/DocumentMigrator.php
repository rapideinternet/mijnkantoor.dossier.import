<?php namespace Migration;

use Exception;
use MijnKantoor\ApiClient;
use MijnKantoor\DossierItem;

class DocumentMigrator
{
    public function __construct(protected $fileSystem, protected ApiClient $mkClient, protected $mutators = [], protected $customerWhitelist = [])
    {

    }

    public function generateMappingTemplate($root, $outputFile): bool
    {
        $uniqueFolders = [];

        try {
            // @todo: fix resume support

            // assume we are listing the folder with the customer folders
            foreach ($this->fileSystem->list($root) as $customerFolder) {
                foreach ($this->fileSystem->traverse($root . "/" . $customerFolder->dirname) as $file) {

                    $segments = explode('/', $file->relativePath);
                    foreach ($segments as $i => $segment) {
                        if (preg_match('/^\d{4}$/', $segment)) {
                            $segments[$i] = "{year}";
                        }
                        if (preg_match('/^Q\d$/', $segment)) {
                            $segments[$i] = "{quarter}";
                        }
                        if (preg_match('/^\b(0?[1-9]|1[0-2])\b$/', $segment)) {
                            $segments[$i] = "{month}";
                        }
                    }
                    $path = implode('/', $segments);

                    echo $path . PHP_EOL;

                    if (!isset($uniqueFolders[$path])) {
                        file_put_contents($outputFile, $path . PHP_EOL, FILE_APPEND);
                        $uniqueFolders[$path] = $file->relativePath;
                    }
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

        foreach ($this->fileSystem->list($root) as $customerFolder) {
            // @todo: fix resume support

            // if customer whitelist is set, skip all other customers
            if (count($this->customerWhitelist) && !in_array($customerFolder->dirname, $this->customerWhitelist)) {
                continue;
            }

            foreach ($this->fileSystem->traverse($root . "/" . $customerFolder->dirname) as $file) {
                // create the target dossier item for MijnKantoor
                $dossierItem = new DossierItem(
                    filename: $file->filename,
                );

                // run the mutators to enrich the dossierItem object
                foreach ($this->mutators as $mutator) {
                    $dossierItem = $mutator->handle($file, $dossierItem);
                }

                // target folder needs to be set by now
                if (!$dossierItem->destDir) {
                    throw new Exception('Destination dir not set for file: ' . $file);
                }

                // translate the destination directory to the id
                $dossierDirectory = $targetDirectories[$dossierItem->destDir] ?? throw new Exception('Destination dir not found: ' . $dossierItem->destDir);
                $dossierItem->destDirId = $dossierDirectory->id;

                // translate the customer number to customer id
                $customerId = $customers[$dossierItem->customerNumber] ?? throw new Exception('Customer not found: ' . $dossierItem->customerNumber);
                $dossierItem->customerId = $customerId->id;

                // get the file content
                $content = $this->fileSystem->getContent($file);

                echo "Migrating file: '" . $customerFolder . "/" . $file->relativePath . "/" . $file->filename . "' to '" . $dossierItem->destDir . "' with id: " . $dossierDirectory->id . PHP_EOL;

                $id = $this->mkClient->uploadDossierItem($dossierItem, $content);

                if ($id) {
                    echo "\tsuccessfully uploaded file with id: " . $id . PHP_EOL . "\n";
                } else {
                    echo "\t Failed to upload file: '" . $customerFolder . "/" . $file->relativePath . "/" . $file->filename . "' to '" . $dossierItem->destDir . "' with id: " . $dossierDirectory->id . PHP_EOL;
                }
            }
        }

        return true;
    }

}