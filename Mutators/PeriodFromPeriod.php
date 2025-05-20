<?php namespace Mutators;

use Exception;
use MijnKantoor\MappedDossierItem;
use SourceFilesystem\File;

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