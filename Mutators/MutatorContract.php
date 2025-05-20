<?php namespace Mutators;

use MijnKantoor\MappedDossierItem;
use SourceFilesystem\File;

interface MutatorContract
{
    public function handle(File $file, MappedDossierItem $dossierItem) : MappedDossierItem;
}