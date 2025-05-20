<?php namespace Mutators;

use Exception;
use MijnKantoor\MappedDossierItem;
use SourceFilesystem\File;

class PeriodFromSourcePath implements MutatorContract
{
    public function __construct()
    {

    }

    public function handle(File $file, MappedDossierItem $dossierItem): MappedDossierItem
    {
        if ($dossierItem->year) {
            return $dossierItem;
        }

        foreach (explode("/", $file->relativePath) as $segment) {
            if (preg_match('/20\d{2}/', $segment, $matches)) {
                $dossierItem->year = $matches[0];
                return $dossierItem;
            }
            if (preg_match('/^Q\d$/', $segment, $matches)) {
                $dossierItem->period = "q" . trim($matches[0], "0");
                return $dossierItem;
            }
            if (preg_match('/^\b(0?[1-9]|1[0-2])\b$/', $segment)) {
                $dossierItem->period = "m" . trim($matches[0], "0");
                return $dossierItem;
            }
        }

        return $dossierItem;
    }
}