<?php namespace Mutators;

use Exception;
use Exceptions\CustomerNotFoundException;
use Mapping\CsvMapper;
use MijnKantoor\MappedDossierItem;
use SourceFilesystem\File;

/*
 * This mutator can be used when the source customer numbers first need to be mapped to new customer numbers in the destination tenant.
 */
class MapCustomerNumbers implements MutatorContract
{
    use ValidateCustomerAndPathRegexTrait;

    protected CsvMapper $mapper;

    public function __construct(string $path)
    {
        $this->mapper = new CsvMapper($path);
    }

    public function handle(File $file, MappedDossierItem $dossierItem): MappedDossierItem
    {
        $newNumber = $this->mapper->getMapping()->get($dossierItem->customerNumber);

        if (!$newNumber) {
            throw new CustomerNotFoundException("Customer number not found in mapping: " . $dossierItem->customerNumber);
        }

        $dossierItem->customerNumber = $newNumber;

        return $dossierItem;
    }
}