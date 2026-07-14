<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\DataExport;

class ExportData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected array $data,
        protected array $headings,
        protected string $filename,
        protected string $title = 'Sheet1',
        protected string $channel = 'products'
    ) {}

    public function handle(): void
    {
        Excel::store(
            new DataExport($this->data, $this->headings, $this->title),
            'exports/' . $this->filename,
            'public'
        );
    }
}
