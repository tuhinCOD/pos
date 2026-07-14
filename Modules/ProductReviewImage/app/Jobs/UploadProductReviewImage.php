<?php

namespace Modules\ProductReviewImage\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Storage;
use Modules\ProductReviewImage\Models\ProductReviewImage;

class UploadProductReviewImage implements ShouldQueue
{
    use Queueable, InteractsWithQueue, Dispatchable, SerializesModels;

    public $filePath;
    public $reviewId;

    /**
     * Create a new job instance.
     */
    public function __construct($filePath, $reviewId)
    {
        $this->filePath = $filePath;
        $this->reviewId = $reviewId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (!Storage::disk('public')->exists($this->filePath)) {
            return;
        }

        $newPath = 'uploads/product_review_images/' . basename($this->filePath);
        Storage::disk('public')->move($this->filePath, $newPath);

        ProductReviewImage::create([
            'product_review_id' => $this->reviewId,
            'image' => $newPath
        ]);
    }
}
