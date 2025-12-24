<?php

namespace UptimeRobot\Entity;

class Monitor
{
    private ?int $id = null;
    private int $userId;
    private string $url;
    private int $intervalSeconds;
    private int $timeoutSeconds;
    private bool $enabled;
    private ?string $discordWebhookUrl;
    private \DateTime $createdAt;
    private \DateTime $updatedAt;

    public function __construct(
        int $userId,
        string $url,
        int $intervalSeconds = 60,
        int $timeoutSeconds = 30,
        bool $enabled = true,
        ?string $discordWebhookUrl = null,
        ?int $id = null,
        ?\DateTime $createdAt = null,
        ?\DateTime $updatedAt = null
    ) {
        $this->userId = $userId;
        $this->url = $url;
        $this->intervalSeconds = $intervalSeconds;
        $this->timeoutSeconds = $timeoutSeconds;
        $this->enabled = $enabled;
        $this->discordWebhookUrl = $discordWebhookUrl;
        $this->id = $id;
        $this->createdAt = $createdAt ?? new \DateTime();
        $this->updatedAt = $updatedAt ?? new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): void
    {
        $this->userId = $userId;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function getIntervalSeconds(): int
    {
        return $this->intervalSeconds;
    }

    public function setIntervalSeconds(int $intervalSeconds): void
    {
        $this->intervalSeconds = $intervalSeconds;
    }

    public function getTimeoutSeconds(): int
    {
        return $this->timeoutSeconds;
    }

    public function setTimeoutSeconds(int $timeoutSeconds): void
    {
        $this->timeoutSeconds = $timeoutSeconds;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getDiscordWebhookUrl(): ?string
    {
        return $this->discordWebhookUrl;
    }

    public function setDiscordWebhookUrl(?string $discordWebhookUrl): void
    {
        $this->discordWebhookUrl = $discordWebhookUrl;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public static function fromArray(array $data): self
    {
        $monitor = new self(
            $data['user_id'],
            $data['url'],
            $data['interval_seconds'] ?? 60,
            $data['timeout_seconds'] ?? 30,
            $data['enabled'] ?? true,
            $data['discord_webhook_url'] ?? null,
            $data['id'] ?? null,
            isset($data['created_at']) ? new \DateTime($data['created_at']) : null,
            isset($data['updated_at']) ? new \DateTime($data['updated_at']) : null
        );
        return $monitor;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'url' => $this->url,
            'interval_seconds' => $this->intervalSeconds,
            'timeout_seconds' => $this->timeoutSeconds,
            'enabled' => $this->enabled,
            'discord_webhook_url' => $this->discordWebhookUrl,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }
}

