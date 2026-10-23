<?php

namespace App\Services\Wecom;

interface WecomClient
{
    public function authorizationUrl(): string;

    public function userIdFromCode(string $code): string;

    /**
     * @return array<int, array{id: int, name: string, parentid: int}>
     */
    public function departments(): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function users(int $departmentId): array;

    /**
     * @return array<int, string>
     */
    public function externalFollowUserIds(): array;

    /**
     * @return array<int, string>
     */
    public function externalContactIds(string $userId): array;

    /**
     * @return array<string, mixed>
     */
    public function externalContact(string $externalUserId): array;
}
