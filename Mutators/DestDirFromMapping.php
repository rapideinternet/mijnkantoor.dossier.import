<?php namespace Mutators;

use Exception;
use Mapping\Mapping;
use MijnKantoor\DossierItem;
use Storage\File;

class DestDirFromMapping implements MutatorContract
{
    public function __construct(protected Mapping $mapping, protected $fallBackDir = null)
    {

    }

    public function handle(File $file, DossierItem $dossierItem): DossierItem
    {
        // trim slashes from the path
        $path = trim($file->relativePath, '/');

        // find the mapping
        foreach ($this->mapping->getMapping() as $sourceDir => $destDir) {

            // normalize the paths
            $destDir = trim($destDir, '/');
            $sourceDir = trim($sourceDir, '/');
            $sourceDir = preg_quote($sourceDir, '/');

            // replace placeholders with regexes
            $sourcePattern = $sourceDir;
            $sourcePattern = str_replace('\{year\}', '\d{4}', $sourcePattern);
            $sourcePattern = str_replace('\{quarter\}', 'Q\d{d}', $sourcePattern);
            $sourcePattern = str_replace('\{month\}', '\b(0?[1-9]|1[0-2])\b', $sourcePattern);

            if (preg_match("/$sourcePattern/", $path)) {
                $dossierItem->destDir = $destDir;
                return $dossierItem;
            }
        }

        // when no mapping is found, use the fallback dir
        if($this->fallBackDir) {
            $dossierItem->destDir = $this->fallBackDir;
        }

        return $dossierItem;
    }
}