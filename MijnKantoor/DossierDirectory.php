<?php namespace MijnKantoor;

class DossierDirectory
{
    public function __construct(
        public $id,
        public $parent_id,
        public $name,
        public $path,
    )
    {

    }
}