<?php

namespace UptimeRobot\Entity;

class MonitorStatus
{
    private ?int $id = null;
    private int $monitorId;
    private string $status; // 'up', 'down'
    private \DateTime $checkedAt;
    private ?int $responseTimeMs;
    private ?int $httpStatusCode;
    private ?string $errorMessage;

    public function __construct(
        int $monitorId,
        string $status,
        ?int $id = null,
        ?\DateTime $checkedAt = null,
        ?int $responseTimeMs = null,
        ?int $httpStatusCode = null,
        ?string $errorMessage = null
    ) {
        $this->monitorId = $monitorId;
        $this->status = $status;
        $this->id = $id;
        $this->checkedAt = $checkedAt ?? new \DateTime();
        $this->responseTimeMs = $responseTimeMs;
        $this->httpStatusCode = $httpStatusCode;
        $this->errorMessage = $errorMessage;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getMonitorId(): int
    {
        return $this->monitorId;
    }

    public function setMonitorId(int $monitorId): void
    {
        $this->monitorId = $monitorId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        if (!in_array($status, ['up', 'down'])) {
            throw new \InvalidArgumentException("Invalid status: {$status}");
        }
        $this->status = $status;
    }

    public function getCheckedAt(): \DateTime
    {
        return $this->checkedAt;
    }

    public function setCheckedAt(\DateTime $checkedAt): void
    {
        $this->checkedAt = $checkedAt;
    }

    public function getResponseTimeMs(): ?int
    {
        return $this->responseTimeMs;
    }

    public function setResponseTimeMs(?int $responseTimeMs): void
    {
        $this->responseTimeMs = $responseTimeMs;
    }

    public function getHttpStatusCode(): ?int
    {
        return $this->httpStatusCode;
    }

    public function setHttpStatusCode(?int $httpStatusCode): void
    {
        $this->httpStatusCode = $httpStatusCode;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): void
    {
        $this->errorMessage = $errorMessage;
    }

    public static function fromArray(array $data): self
    {
        $status = new self(
            $data['monitor_id'],
            $data['status'],
            $data['id'] ?? null,
            isset($data['checked_at']) ? new \DateTime($data['checked_at']) : null,
            $data['response_time_ms'] ?? null,
            $data['http_status_code'] ?? null,
            $data['error_message'] ?? null
        );
        return $status;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'monitor_id' => $this->monitorId,
            'status' => $this->status,
            'checked_at' => $this->checkedAt->format('Y-m-d H:i:s'),
            'response_time_ms' => $this->responseTimeMs,
            'http_status_code' => $this->httpStatusCode,
            'error_message' => $this->errorMessage,
        ];
    }
}

