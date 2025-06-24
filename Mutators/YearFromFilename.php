<?php namespace Mutators;

use Exception;
use MijnKantoor\MappedDossierItem;
use SourceFilesystem\File;

/*
 * This mutator extracts the year from the filename of the file object
 */
class YearFromFilename implements MutatorContract
{
    public function __construct()
    {

    }

    public function handle(File $file, MappedDossierItem $dossierItem): MappedDossierItem
    {
        if ($dossierItem->year) {
            return $dossierItem;
        }

        if (preg_match('/\b20\d{2}\b/', $file->filename, $matches)) {
            $dossierItem->year = $matches[0];
        }

        return $dossierItem;
    }
}