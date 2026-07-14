<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class DataExport implements FromArray, WithHeadings, WithTitle
{
    protected array $data;
    protected array $headings;
    protected string $title;

    public function __construct(array $data, array $headings, string $title = 'Sheet1')
    {
        $this->data = $data;
        $this->headings = $headings;
        $this->title = $title;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function title(): string
    {
        return $this->title;
    }
}
