<?php namespace MijnKantoor;

class MappedDossierItem
{
    public function __construct(
        public string|null $customerNumber = null,
        public string|null $filename = null,
        public int|null $year = null,
        public string|null $period = null,
        public string|null $relativeSourceDir = null, // this is the relative path starting from the customer dir
        public string|null $sourceFilename = null, // before mutators are applied
        public string|null $destDir = null,
        public string|null $destDirId = null,
        public string|null $customerId = null,
        public string|null $sourceDirId = null,
    )
    {

    }

    public function __toString(): string
    {
        $string = 'MappedDossierItem (destDir=' . $this->destDir . ')';

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