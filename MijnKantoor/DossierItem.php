<?php namespace MijnKantoor;

class DossierItem
{
    public function __construct(
        public $id,
        public $original_filename,
        public $name,
        public $dossier_directory_id,
        public $customer_id,
        public $created_at = null,
        public $year = null,
        public $period = null,
        public $parentId = null,
    )
    {

    }
}