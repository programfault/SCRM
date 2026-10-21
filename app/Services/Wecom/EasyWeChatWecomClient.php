<?php

namespace App\Services\Wecom;

use App\Models\WecomSetting;
use EasyWeChat\Work\Application;

class EasyWeChatWecomClient implements WecomClient
{
    public function authorizationUrl(): string
    {
        $response = $this->application()->getOAuth()->redirect();

        return $response instanceof \Stringable || is_string($response)
            ? (string) $response
            : $response->getTargetUrl();
    }

    public function userIdFromCode(string $code): string
    {
        $userId = $this->application()->getOAuth()->userFromCode($code)->getId();

        if (! is_string($userId) || $userId === '') {
            throw new WecomRequestFailed('企业微信没有返回人员编号。');
        }

        return $userId;
    }

    public function departments(): array
    {
        $payload = $this->get('/cgi-bin/department/list');

        return array_map(fn (array $department): array => [
            'id' => (int) $department['id'],
            'name' => (string) $department['name'],
            'parentid' => (int) ($department['parentid'] ?? 0),
        ], $payload['department'] ?? []);
    }

    public function users(int $departmentId): array
    {
        $payload = $this->get('/cgi-bin/user/list', [
            'department_id' => $departmentId,
        ]);

        return $payload['userlist'] ?? [];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(string $url, array $query = []): array
    {
        $payload = $this->application()->getClient()->get($url, [
            'query' => $query,
        ])->toArray();

        $code = (int) ($payload['errcode'] ?? 0);

        if ($code !== 0) {
            throw new WecomRequestFailed((string) ($payload['errmsg'] ?? '企业微信接口失败'));
        }

        return $payload;
    }

    private function application(): Application
    {
        $setting = WecomSetting::current();

        if ($setting === null || ! $setting->isReady()) {
            throw WecomNotConfigured::make();
        }

        return new Application([
            'corp_id' => $setting->corp_id,
            'secret' => $setting->secret,
            'token' => 'unused',
            'aes_key' => 'unused',
            'agent_id' => (int) $setting->agent_id,
            'oauth' => [
                'redirect_url' => route('wecom.callback'),
                'scopes' => ['snsapi_base'],
            ],
        ]);
    }
}
