<?php

namespace App\Services\Wecom;

use RuntimeException;

class WecomNotConfigured extends RuntimeException
{
    public static function make(): self
    {
        return new self('请先在系统配置里填写企业微信参数。');
    }
}
