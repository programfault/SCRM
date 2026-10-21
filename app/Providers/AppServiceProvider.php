<?php

namespace App\Providers;

use App\Services\Wecom\EasyWeChatWecomClient;
use App\Services\Wecom\WecomClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(WecomClient::class, EasyWeChatWecomClient::class);
    }
}
