<?php namespace Mutators;

use Exception;
use MijnKantoor\MappedDossierItem;
use SourceFilesystem\File;

/*
 * This mutator copies the period from the file object to the dossier item.
 * Only for MijnKantoor as source, as it has a period property.
 */
class PeriodFromPeriod implements MutatorContract
{
    public function __construct()
    {

    }

    public function handle(File $file, MappedDossierItem $dossierItem): MappedDossierItem
    {
        $dossierItem->period = $file->period ?? null;

        return $dossierItem;
    }
}