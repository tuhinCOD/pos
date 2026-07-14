<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExportComplete implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $filename,
        public string $channel
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel($this->channel),
        ];
    }

    public function broadcastAs()
    {
        return 'export-complete';
    }
}
