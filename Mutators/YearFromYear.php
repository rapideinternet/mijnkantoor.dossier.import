<?php namespace Mutators;

use Exception;
use MijnKantoor\MappedDossierItem;
use SourceFilesystem\File;

/**
 * This mutator copies the year from the file object to the dossier item.
 * Only for MijnKantoor as source, as it has a year property.
 */
class YearFromYear implements MutatorContract
{
    public function __construct()
    {

    }

    public function handle(File $file, MappedDossierItem $dossierItem): MappedDossierItem
    {
        $dossierItem->year = $file->year ?? null;

        return $dossierItem;
    }
}