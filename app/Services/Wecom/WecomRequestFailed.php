<?php

namespace App\Services\Wecom;

use RuntimeException;

class WecomRequestFailed extends RuntimeException
{
    public function __construct(string $message, public readonly int $wecomCode = 0)
    {
        parent::__construct($message);
    }
}
