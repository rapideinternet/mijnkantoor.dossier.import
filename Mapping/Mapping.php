<?php namespace Mapping;

class Mapping
{
    protected array $mapping = [];

    public function __construct()
    {

    }

    public function add($source, $destination): void
    {
        $this->mapping[$source] = $destination;
    }

    public function get($source)
    {
        return $this->mapping[$source] ?? null;
    }

    public function getMapping(): array
    {
        return $this->mapping;
    }
}