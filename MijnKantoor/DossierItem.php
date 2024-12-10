<?php namespace MijnKantoor;

class DossierItem
{
    public function __construct(
        public string|null $customerNumber = null,
        public string|null $filename = null,
        public int|null $year = null,
        public string|null $period = null,
        public string|null $destDir = null,
        public string|null $destDirId = null,
        public string|null $customerId = null,
    )
    {

    }

    public function isValid(): bool
    {
        $required = ['customerNumber', 'year'];

        return array_reduce($required, function ($carry, $item) {
            return $carry && !empty($this->$item);
        }, true);
    }

    public function __toString(): string
    {
        $string = 'DossierItem (destDir=' . $this->destDir . ')';

        // also add customerNumber, year and period if they are set
        if ($this->customerNumber) {
            $string .= ' (customer_number=' . $this->customerNumber . ')';
        }
        if ($this->year) {
            $string .= ' (year=' . $this->year . ')';
        }
        if ($this->period) {
            $string .= ' (period=' . $this->period . ')';
        }

        return $string;
    }
}