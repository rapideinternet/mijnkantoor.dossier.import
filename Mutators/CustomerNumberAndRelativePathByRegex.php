<?php namespace Mutators;

use Exception;
use Exceptions\CustomerNotFoundException;
use MijnKantoor\MappedDossierItem;
use SourceFilesystem\File;

class CustomerNumberAndRelativePathByRegex implements MutatorContract
{
    use ValidateCustomerAndPathRegexTrait;

    public function __construct(protected string $pattern)
    {
        $this->validateCustomerAndPathRegex($pattern);
    }

    public function handle(File $file, MappedDossierItem $dossierItem): MappedDossierItem
    {
        if (preg_match($this->pattern, $file->absolutePath, $matches)) {
            $dossierItem->customerNumber = ltrim($matches['number'], '0');

            $dossierItem->relativeSourceDir = $matches['relativePath'];


            return $dossierItem;
        }

        throw new CustomerNotFoundException('Customer number not found for path: ' . $file->absolutePath);
    }
}