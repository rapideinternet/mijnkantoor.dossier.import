<?php namespace Mutators;

use MijnKantoor\DossierItem;
use Storage\File;

interface MutatorContract
{
    public function handle(File $file, DossierItem $dossierItem) : DossierItem;
}