<?php namespace Mutators;

use InvalidArgumentException;

Trait ValidateCustomerAndPathRegexTrait
{
    public function validateCustomerAndPathRegex(string $pattern): void
    {
        // Example pattern: '/\/\d+\s*-\s*(?P<name>.*?)\s*-\s*\((?P<number>\d+)\)\/(?P<relativePath>.*)$/'

        // Ensure the regex compiles
        if (@preg_match($pattern, '') === false) {
            throw new InvalidArgumentException('Invalid regex pattern provided.');
        }

        // Extract all named groups in the regex
        preg_match_all('/\?P<(\w+)>/', $pattern, $matches);

        // Check if the required groups are present
        $requiredGroups = ['name', 'number', 'relativePath'];
        $missingGroups = array_diff($requiredGroups, $matches[1]);

        if (!empty($missingGroups)) {
            throw new InvalidArgumentException(
                'The provided regex is missing required named groups: ' . implode(', ', $missingGroups)
            );
        }
    }
}