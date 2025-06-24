<?php namespace Mutators;

use Exception;
use Exceptions\CustomerNotFoundException;
use Mapping\Mapping;
use MijnKantoor\MappedDossierItem;
use SourceFilesystem\File;

/*
 * This mutator can be used to implement custom destination directories based on regex rules.
 * For example, when customer want's to determine the destination directory based on certain parts in the filename.
 */
class DestDirFromRegex implements MutatorContract
{
    public function __construct(protected array $rules)
    {

    }

    public function handle(File $file, MappedDossierItem $dossierItem): MappedDossierItem
    {
        if (!$dossierItem->relativeSourceDir) {
            throw new Exception("No relative source directory set while determining destination directory");
        }

        // if filename matches one of the regex rules in the rules array, set the destDir to the corresponding value
        foreach ($this->rules as $rule => $destDir) {
            if (preg_match($rule, $file)) {
                $dossierItem->destDir = $destDir;
                return $dossierItem;
            }
        }

        return $dossierItem;
    }
}