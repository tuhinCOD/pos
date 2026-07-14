<?php

namespace Modules\Unit\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UnitUpdate implements ShouldBroadcast {
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $unit;

    public function __construct($unit)
    {
        $this->unit = $unit;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('units'),
        ];
    }

    public function broadcastAs()
    {
        return 'unit-updated';
    }
}

