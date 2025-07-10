<?php namespace SourceFilesystem;

use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use MijnKantoor\ApiClient;

class MijnKantoor implements FilesystemContract
{
    use ProcessedLogTrait;

    private ApiClient $client;

    public function __construct(protected array $config = [])
    {
        // use the destination client as source, but with different config
        $this->client = new ApiClient($config);
    }

    public function traverse(string|null $root = null): \Generator
    {
        $debug = $this->config['debug'] ?? false;

        // take of where we left off by skipping the already processed directories
        $this->loadProcessedItemLog();

        foreach ($this->client->allCustomerByNumber() as $customer) {
            $dirs = $this->client->allDirectoriesWithParentAndPath(10000, true, $customer->id);

            // Fetch all files for the customer
            foreach ($this->client->allDossierItemsByCustomer($customer->id) as $item) {

                if ($this->itemProcessed($item->id)) {
                    if ($debug) {
                        echo "\tWarning: Skipping (already processed)\n";
                    }
                    continue;
                }

                $relativePath = $customer->number . ' - ' . $customer->name . '/' . ($dirs[$item->dossier_directory_id]->path ?? "directory_not_found");

                yield (new File(
                    filename: $item->name, // customer may have changed the original filename
                    absolutePath: $relativePath, // there is no absolute path in the API
                    relativePath: $relativePath,
                    id: $item->id,
                    createdAt: Carbon::parse($item->created_at),
                    year: $item->year,
                    period: $item->period,
                    parentId: $item->parentId ?? null,
                ));

                $this->addItemToProcessedLog($item->id);
            }
        }
    }

    public function getContent($file): string
    {
        return $this->client->downloadDossierItem($file->id);
    }
}