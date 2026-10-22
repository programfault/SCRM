<?php

namespace App\Jobs;

use App\Models\SyncLog;
use App\Services\Wecom\SyncWecom;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncWecomJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public ?int $triggeredBy = null, public ?int $syncLogId = null) {}

    public function handle(SyncWecom $sync): void
    {
        $log = $this->syncLogId === null ? null : SyncLog::query()->find($this->syncLogId);

        if ($log !== null) {
            $sync->execute($log);

            return;
        }

        $sync->run($this->triggeredBy);
    }
}
