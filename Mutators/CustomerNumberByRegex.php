<?php namespace Mutators;

use Exception;
use Exceptions\CustomerNotFoundException;
use MijnKantoor\DossierItem;
use Storage\File;

class CustomerNumberByRegex implements MutatorContract
{
    public function __construct(protected string $pattern)
    {

    }

    public function handle(File $file, DossierItem $dossierItem): DossierItem
    {
        if (preg_match($this->pattern, $file->absolutePath, $matches)) {
            $dossierItem->customerNumber = $matches[count($matches) - 1];

            return $dossierItem;
        }

        throw new CustomerNotFoundException('Customer number not found for path: ' . $file->absolutePath);
    }
}