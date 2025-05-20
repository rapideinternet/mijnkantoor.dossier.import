<?php namespace Mutators;

use Exception;
use MijnKantoor\MappedDossierItem;
use SourceFilesystem\File;

class YearFromSourcePath implements MutatorContract
{
    public function __construct()
    {

    }

    public function handle(File $file, MappedDossierItem $dossierItem): MappedDossierItem
    {
        if ($dossierItem->year) {
            return $dossierItem;
        }

        foreach(explode("/", $file->relativePath) as $segment) {
            // @todo: support template such as "Boekjaar {year}" in one segment
            if (preg_match('/20\d{2}/', $segment, $matches)) {
                $dossierItem->year = $matches[0];
                return $dossierItem;
            }
        }

        return $dossierItem;
    }
}