<?php namespace Mutators;

use Exception;
use Exceptions\CustomerNotFoundException;
use MijnKantoor\MappedDossierItem;
use SourceFilesystem\File;

class CustomerNumberAndRelativePathByRegex implements MutatorContract
{
    use ValidateCustomerAndPathRegexTrait;

    public function __construct(protected string $pattern, protected $lTrim = false)
    {
        $this->validateCustomerAndPathRegex($pattern);
    }

    public function handle(File $file, MappedDossierItem $dossierItem): MappedDossierItem
    {
        $requiredMatches = ['number', 'relativePath', 'name'];
        if (preg_match($this->pattern, $file->absolutePath, $matches)) {
            foreach($requiredMatches as $match) {
                if (!isset($matches[$match])) {
                    throw new CustomerNotFoundException("Required match '$match' not found in path: " . $file->absolutePath);
                }
            }

            if ($this->lTrim) {
                $matches['number'] = ltrim($matches['number'], '0');
            }

            $dossierItem->customerNumber = $matches['number'];
            $dossierItem->relativeSourceDir = $matches['relativePath'];


            return $dossierItem;
        }

        throw new CustomerNotFoundException('Customer number not found for path: ' . $file->absolutePath);
    }
}