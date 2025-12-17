<?php

namespace Stumason\Coolify\Repositories;

use Stumason\Coolify\Contracts\SecurityKeyRepository;
use Stumason\Coolify\CoolifyClient;

class CoolifySecurityKeyRepository implements SecurityKeyRepository
{
    public function __construct(
        protected CoolifyClient $client
    ) {}

    /**
     * @inheritDoc
     */
    public function all(): array
    {
        return $this->client->get('security/keys');
    }

    /**
     * @inheritDoc
     */
    public function get(string $uuid): array
    {
        return $this->client->get("security/keys/{$uuid}");
    }

    /**
     * @inheritDoc
     */
    public function create(array $data): array
    {
        return $this->client->post('security/keys', $data);
    }

    /**
     * @inheritDoc
     */
    public function update(string $uuid, array $data): array
    {
        return $this->client->patch("security/keys/{$uuid}", $data);
    }

    /**
     * @inheritDoc
     */
    public function delete(string $uuid): bool
    {
        $this->client->delete("security/keys/{$uuid}");

        return true;
    }
}
