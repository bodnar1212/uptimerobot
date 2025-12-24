<?php

namespace UptimeRobot\Repository;

use UptimeRobot\Database\Connection;
use UptimeRobot\Entity\Monitor;
use PDO;

class MonitorRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::getInstance();
    }

    public function findById(int $id): ?Monitor
    {
        $stmt = $this->db->prepare('SELECT * FROM monitors WHERE id = ?');
        $stmt->execute([$id]);
        $data = $stmt->fetch();

        return $data ? Monitor::fromArray($data) : null;
    }

    public function findByUserId(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM monitors WHERE user_id = ? ORDER BY created_at DESC');
        $stmt->execute([$userId]);
        $data = $stmt->fetchAll();

        return array_map(fn($row) => Monitor::fromArray($row), $data);
    }

    public function findEnabled(): array
    {
        $stmt = $this->db->query('SELECT * FROM monitors WHERE enabled = 1 ORDER BY id');
        $data = $stmt->fetchAll();

        return array_map(fn($row) => Monitor::fromArray($row), $data);
    }

    public function create(Monitor $monitor): Monitor
    {
        $stmt = $this->db->prepare(
            'INSERT INTO monitors (user_id, url, interval_seconds, timeout_seconds, enabled, discord_webhook_url, telegram_bot_token, telegram_chat_id, created_at, updated_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $monitor->getUserId(),
            $monitor->getUrl(),
            $monitor->getIntervalSeconds(),
            $monitor->getTimeoutSeconds(),
            $monitor->isEnabled() ? 1 : 0,
            $monitor->getDiscordWebhookUrl(),
            $monitor->getTelegramBotToken(),
            $monitor->getTelegramChatId(),
            $monitor->getCreatedAt()->format('Y-m-d H:i:s'),
            $monitor->getUpdatedAt()->format('Y-m-d H:i:s'),
        ]);

        $monitor->setId((int)$this->db->lastInsertId());
        return $monitor;
    }

    public function update(Monitor $monitor): void
    {
        $stmt = $this->db->prepare(
            'UPDATE monitors SET url = ?, interval_seconds = ?, timeout_seconds = ?, enabled = ?, discord_webhook_url = ?, telegram_bot_token = ?, telegram_chat_id = ?, updated_at = ? WHERE id = ?'
        );
        $stmt->execute([
            $monitor->getUrl(),
            $monitor->getIntervalSeconds(),
            $monitor->getTimeoutSeconds(),
            $monitor->isEnabled() ? 1 : 0,
            $monitor->getDiscordWebhookUrl(),
            $monitor->getTelegramBotToken(),
            $monitor->getTelegramChatId(),
            $monitor->getUpdatedAt()->format('Y-m-d H:i:s'),
            $monitor->getId(),
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM monitors WHERE id = ?');
        $stmt->execute([$id]);
    }
}

