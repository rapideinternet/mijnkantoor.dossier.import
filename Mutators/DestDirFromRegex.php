<?php namespace Mutators;

use Exception;
use Exceptions\CustomerNotFoundException;
use Mapping\Mapping;
use MijnKantoor\DossierItem;
use Storage\File;

class DestDirFromRegex implements MutatorContract
{


    public function __construct(protected array $rules)
    {

    }

    public function handle(File $file, DossierItem $dossierItem): DossierItem
    {
        if (!$dossierItem->relativeSourceDir) {
            throw new Exception("No relative source directory set while determining destination directory");
        }

        // if filename matches one of the regex rules in the rules array, set the destDir to the corresponding value
        foreach ($this->rules as $rule => $destDir) {
            if (preg_match($rule, $dossierItem->relativeSourceDir)) {
                $dossierItem->destDir = $destDir;
                return $dossierItem;
            }
        }

        return $dossierItem;
    }
}