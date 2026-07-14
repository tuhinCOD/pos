<?php

namespace Modules\Credit\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CreditUpdate implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $credit;

    public function __construct($credit)
    {
        $this->credit = $credit;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('credits'),
        ];
    }

    public function broadcastAs()
    {
        return 'credit-updated';
    }
}
