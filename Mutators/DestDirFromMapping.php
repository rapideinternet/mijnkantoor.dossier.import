<?php namespace Mutators;

use Exception;
use Exceptions\CustomerNotFoundException;
use Mapping\CsvMapper;
use Mapping\Mapping;
use MijnKantoor\MappedDossierItem;
use SourceFilesystem\File;

/*
 * This mutator determines the destination directory for a file based on a mapping file provided by the customer.
 */
class DestDirFromMapping implements MutatorContract
{

    protected Mapping $mapping;

    public function __construct(string $path, protected $fallBackDir = null)
    {
        $this->mapping = (new CsvMapper($path))->getMapping();
    }

    public function handle(File $file, MappedDossierItem $dossierItem): MappedDossierItem
    {
        if (!$dossierItem->customerNumber) {
            throw new CustomerNotFoundException("No customer number set while determining destination directory");
        }

        if (!$dossierItem->relativeSourceDir) {
            throw new Exception("No relative source directory set while determining destination directory");
        }

        // trim slashes from the path
        $path = trim($dossierItem->relativeSourceDir, '/') . '/';


        // find the mapping
        foreach ($this->mapping as $sourceDir => $destDir) {

            // normalize the paths
            $destDir = trim($destDir, '/');
            $destDir = strtolower($destDir);
            $sourceDir = trim($sourceDir, '/') . '/';
            $sourceDir = preg_quote($sourceDir, '/');

            // replace placeholders with regexes
            $sourcePattern = $sourceDir;
            $sourcePattern = str_replace('\{year\}', '\d{4}', $sourcePattern);
            $sourcePattern = str_replace('\{quarter\}', 'Q\d{d}', $sourcePattern);
            $sourcePattern = str_replace('\{month\}', '\b(0?[1-9]|1[0-2])\b', $sourcePattern);

            if (preg_match('/^' . $sourcePattern . '/i', $path)) {
                $dossierItem->destDir = $destDir;
                return $dossierItem;
            }
        }

        // when no mapping is found, use the fallback dir
        if ($this->fallBackDir) {
            $dossierItem->destDir = $this->fallBackDir;

            // also sluggify the source dir and prefix it to the filename to have some reference
            $dossierItem->filename = $this->slugify($file->relativePath) . '-' . $dossierItem->filename;
        }

        return $dossierItem;
    }

    function slugify(string $text): string {
        // Replace non-letter or digits by -
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);

        // Transliterate (convert to ASCII)
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

        // Remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);

        // Trim
        $text = trim($text, '-');

        // Remove duplicate -
        $text = preg_replace('~-+~', '-', $text);

        // Lowercase
        $text = strtolower($text);

        return empty($text) ? 'n-a' : $text;
    }

}