<?php namespace Mapping;

class CsvMapper
{
    public function __construct(protected string $csvFile)
    {

    }

    public function getMapping(): Mapping
    {
        $mapping = new Mapping();

        $handle = fopen($this->csvFile, 'r');

        // Check and remove BOM if present
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            // No BOM, rewind the file pointer
            rewind($handle);
        }

        while (($line = fgetcsv(
                stream: $handle,
                length: 1000,
                separator: ';',
                escape: '"',
            )) !== false) {
            $mapping->add($line[0], $line[1]);
        }
        fclose($handle);

        return $mapping;
    }
}