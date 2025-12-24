<?php

namespace UptimeRobot\Entity;

class User
{
    private ?int $id = null;
    private string $email;
    private string $apiKey;
    private \DateTime $createdAt;

    public function __construct(
        string $email,
        string $apiKey,
        ?int $id = null,
        ?\DateTime $createdAt = null
    ) {
        $this->email = $email;
        $this->apiKey = $apiKey;
        $this->id = $id;
        $this->createdAt = $createdAt ?? new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function setApiKey(string $apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public static function fromArray(array $data): self
    {
        $user = new self(
            $data['email'],
            $data['api_key'],
            $data['id'] ?? null,
            isset($data['created_at']) ? new \DateTime($data['created_at']) : null
        );
        return $user;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'api_key' => $this->apiKey,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}

