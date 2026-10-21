<?php

namespace App\Jobs;

use App\Services\Wecom\SyncWecom;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncWecomJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public ?int $triggeredBy = null) {}

    public function handle(SyncWecom $sync): void
    {
        $sync->run($this->triggeredBy);
    }
}
