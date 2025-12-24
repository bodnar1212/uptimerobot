<?php

namespace UptimeRobot\Service;

use UptimeRobot\Entity\Monitor;
use UptimeRobot\Repository\MonitorRepository;
use UptimeRobot\Repository\QueueRepository;
use InvalidArgumentException;

class MonitorService
{
    private MonitorRepository $monitorRepository;
    private QueueRepository $queueRepository;

    public function __construct(
        MonitorRepository $monitorRepository,
        QueueRepository $queueRepository
    ) {
        $this->monitorRepository = $monitorRepository;
        $this->queueRepository = $queueRepository;
    }

    public function create(
        int $userId,
        string $url,
        int $intervalSeconds = 60,
        int $timeoutSeconds = 30,
        bool $enabled = true,
        ?string $discordWebhookUrl = null,
        ?string $telegramBotToken = null,
        ?string $telegramChatId = null
    ): Monitor {
        $this->validateUrl($url);
        $this->validateInterval($intervalSeconds);
        $this->validateTimeout($timeoutSeconds);

        $monitor = new Monitor(
            $userId,
            $url,
            $intervalSeconds,
            $timeoutSeconds,
            $enabled,
            $discordWebhookUrl,
            $telegramBotToken,
            $telegramChatId
        );

        $monitor = $this->monitorRepository->create($monitor);

        // Schedule initial check if enabled
        if ($monitor->isEnabled()) {
            $this->scheduleCheck($monitor);
        }

        return $monitor;
    }

    public function update(
        int $monitorId,
        int $userId,
        ?string $url = null,
        ?int $intervalSeconds = null,
        ?int $timeoutSeconds = null,
        ?bool $enabled = null,
        ?string $discordWebhookUrl = null,
        ?string $telegramBotToken = null,
        ?string $telegramChatId = null
    ): Monitor {
        $monitor = $this->monitorRepository->findById($monitorId);

        if (!$monitor) {
            throw new InvalidArgumentException("Monitor not found: {$monitorId}");
        }

        if ($monitor->getUserId() !== $userId) {
            throw new InvalidArgumentException("Monitor does not belong to user");
        }

        if ($url !== null) {
            $this->validateUrl($url);
            $monitor->setUrl($url);
        }

        if ($intervalSeconds !== null) {
            $this->validateInterval($intervalSeconds);
            $monitor->setIntervalSeconds($intervalSeconds);
        }

        if ($timeoutSeconds !== null) {
            $this->validateTimeout($timeoutSeconds);
            $monitor->setTimeoutSeconds($timeoutSeconds);
        }

        $wasEnabled = $monitor->isEnabled();

        if ($enabled !== null) {
            $monitor->setEnabled($enabled);
        }

        if ($discordWebhookUrl !== null) {
            $monitor->setDiscordWebhookUrl($discordWebhookUrl);
        }

        if ($telegramBotToken !== null) {
            $monitor->setTelegramBotToken($telegramBotToken);
        }

        if ($telegramChatId !== null) {
            $monitor->setTelegramChatId($telegramChatId);
        }

        $monitor->setUpdatedAt(new \DateTime());
        $this->monitorRepository->update($monitor);

        // Schedule check if newly enabled
        if ($enabled === true && !$wasEnabled) {
            $this->scheduleCheck($monitor);
        }

        return $monitor;
    }

    public function delete(int $monitorId, int $userId): void
    {
        $monitor = $this->monitorRepository->findById($monitorId);

        if (!$monitor) {
            throw new InvalidArgumentException("Monitor not found: {$monitorId}");
        }

        if ($monitor->getUserId() !== $userId) {
            throw new InvalidArgumentException("Monitor does not belong to user");
        }

        $this->monitorRepository->delete($monitorId);
    }

    public function getByUserId(int $userId): array
    {
        return $this->monitorRepository->findByUserId($userId);
    }

    public function getById(int $monitorId, int $userId): ?Monitor
    {
        $monitor = $this->monitorRepository->findById($monitorId);

        if ($monitor && $monitor->getUserId() === $userId) {
            return $monitor;
        }

        return null;
    }

    private function validateUrl(string $url): void
    {
        if (empty($url)) {
            throw new InvalidArgumentException("URL cannot be empty");
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException("Invalid URL format: {$url}");
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'])) {
            throw new InvalidArgumentException("URL must use http or https scheme");
        }
    }

    private function validateInterval(int $intervalSeconds): void
    {
        if ($intervalSeconds < 60) {
            throw new InvalidArgumentException("Interval must be at least 60 seconds");
        }

        if ($intervalSeconds > 86400) {
            throw new InvalidArgumentException("Interval cannot exceed 86400 seconds (24 hours)");
        }
    }

    private function validateTimeout(int $timeoutSeconds): void
    {
        if ($timeoutSeconds < 1) {
            throw new InvalidArgumentException("Timeout must be at least 1 second");
        }

        if ($timeoutSeconds > 300) {
            throw new InvalidArgumentException("Timeout cannot exceed 300 seconds");
        }
    }

    private function scheduleCheck(Monitor $monitor): void
    {
        $scheduledAt = new \DateTime();
        $this->queueRepository->createJob($monitor->getId(), $scheduledAt);
    }
}

