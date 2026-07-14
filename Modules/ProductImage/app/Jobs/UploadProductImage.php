<?php

namespace Modules\ProductImage\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Storage;
use Modules\ProductImage\Models\ProductImage;

class UploadProductImage implements ShouldQueue
{
    use Queueable, InteractsWithQueue, Dispatchable, SerializesModels;

    public $filePath;
    public $productId;
    public $title;

    /**
     * Create a new job instance.
     */
    public function __construct($filePath, $productId, $title)
    {
        $this->filePath = $filePath;
        $this->productId = $productId;
        $this->title = $title;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (!Storage::disk('public')->exists($this->filePath)) {
            return;
        }

        $newPath = 'uploads/product_images/' . basename($this->filePath);
        Storage::disk('public')->move($this->filePath, $newPath);

        ProductImage::create([
            'product_id' => $this->productId,
            'image' => $this->filePath,
            'title' => $this->title
        ]);
    }
}
