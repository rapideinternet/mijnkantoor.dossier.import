<?php namespace MijnKantoor;

class DossierDirectory
{
    public function __construct(
        public $id,
        public $parent_id,
        public $is_leaf,
        public $name,
        public $path,
    )
    {

    }
}