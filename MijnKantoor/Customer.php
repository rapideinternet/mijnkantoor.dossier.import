<?php namespace MijnKantoor;

class Customer
{
    public function __construct(
        public string $id,
        public string $name,
        public string $number,
    )
    {

    }
}