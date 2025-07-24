<?php namespace Migration;

use Carbon\Carbon;
use Exception;
use Exceptions\CustomerNotFoundException;
use MijnKantoor\ApiClient;
use MijnKantoor\MappedDossierItem;
use SourceFilesystem\FilesystemContract;

class MappingGenerator
{
    public function __construct(
        protected FilesystemContract $fileSystem,
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
                $relativePath = $matches['relativePath'];

                if (!$customerIdentifier) {
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
}