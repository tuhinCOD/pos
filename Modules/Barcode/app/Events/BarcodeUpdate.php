<?php

namespace Modules\Barcode\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BarcodeUpdate implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $barcode;

    public function __construct($barcode)
    {
        $this->barcode = $barcode;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('barcodes'),
        ];
    }

    public function broadcastAs()
    {
        return 'barcode-updated';
    }
}
