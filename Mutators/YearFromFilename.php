<?php namespace Mutators;

use Exception;
use MijnKantoor\DossierItem;
use Storage\File;

class YearFromFilename implements MutatorContract
{
    public function __construct()
    {

    }

    public function handle(File $file, DossierItem $dossierItem): DossierItem
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