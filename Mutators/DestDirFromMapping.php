<?php namespace Mutators;

use Exception;
use Exceptions\CustomerNotFoundException;
use Mapping\Mapping;
use MijnKantoor\MappedDossierItem;
use SourceFilesystem\File;

class DestDirFromMapping implements MutatorContract
{


    public function __construct(protected Mapping $mapping, protected $fallBackDir = null)
    {

    }

    public function handle(File $file, MappedDossierItem $dossierItem): MappedDossierItem
    {
        if (!$dossierItem->customerNumber) {
            throw new CustomerNotFoundException("No customer number set while determining destination directory");
        }

        if (!$dossierItem->relativeSourceDir) {
            throw new Exception("No relative source directory set while determining destination directory");
        }

        // trim slashes from the path
        $path = trim($dossierItem->relativeSourceDir, '/') . '/';


        // find the mapping
        foreach ($this->mapping->getMapping() as $sourceDir => $destDir) {

            // normalize the paths
            $destDir = trim($destDir, '/');
            $destDir = strtolower($destDir);
            $sourceDir = trim($sourceDir, '/') . '/';
            $sourceDir = preg_quote($sourceDir, '/');

            // replace placeholders with regexes
            $sourcePattern = $sourceDir;
            $sourcePattern = str_replace('\{year\}', '\d{4}', $sourcePattern);
            $sourcePattern = str_replace('\{quarter\}', 'Q\d{d}', $sourcePattern);
            $sourcePattern = str_replace('\{month\}', '\b(0?[1-9]|1[0-2])\b', $sourcePattern);

            if (preg_match('/^' . $sourcePattern . '/i', $path)) {
                $dossierItem->destDir = $destDir;
                return $dossierItem;
            }
        }

        // when no mapping is found, use the fallback dir
        if ($this->fallBackDir) {
            $dossierItem->destDir = $this->fallBackDir;
        }

        return $dossierItem;
    }
}