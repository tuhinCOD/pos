<?php

namespace Modules\Company\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Storage;
use Modules\Company\Models\Company;

class UploadCompanyLogo implements ShouldQueue
{
    use Queueable, InteractsWithQueue, Dispatchable, SerializesModels;

    public $filePath;
    public $companyId;

    /**
     * Create a new job instance.
     */
    public function __construct($filePath, $companyId)
    {
        $this->filePath = $filePath;
        $this->companyId = $companyId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $company = Company::find($this->companyId);
        if (!$company) return;

        $newPath = 'company_logo/' . basename($this->filePath);
        Storage::disk('public')->move($this->filePath, $newPath);

        $company->logo = $newPath;
        $company->save();
    }
}
