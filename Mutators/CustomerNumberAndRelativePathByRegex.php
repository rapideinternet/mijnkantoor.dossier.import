<?php namespace Mutators;

use Exception;
use Exceptions\CustomerNotFoundException;
use MijnKantoor\DossierItem;
use Storage\File;

class CustomerNumberAndRelativePathByRegex implements MutatorContract
{
    use ValidateCustomerAndPathRegexTrait;

    public function __construct(protected string $pattern)
    {
        $this->validateCustomerAndPathRegex($pattern);
    }

    public function handle(File $file, DossierItem $dossierItem): DossierItem
    {
        if (preg_match($this->pattern, $file->absolutePath, $matches)) {
            $dossierItem->customerNumber = ltrim($matches['number'], '0');

            $dossierItem->relativeSourceDir = $matches['relativePath'];


            return $dossierItem;
        }

        throw new CustomerNotFoundException('Customer number not found for path: ' . $file->absolutePath);
    }
}