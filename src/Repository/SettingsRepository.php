<?php

namespace UptimeRobot\Repository;

use UptimeRobot\Database\Connection;

class SettingsRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Connection::getInstance();
    }

    /**
     * Get a setting value by key
     */
    public function get(string $key, ?string $default = null): ?string
    {
        $stmt = $this->pdo->prepare('SELECT value FROM settings WHERE `key` = ?');
        $stmt->execute([$key]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $result ? $result['value'] : $default;
    }

    /**
     * Get all settings
     */
    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM settings ORDER BY `key`');
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Set a setting value
     */
    public function set(string $key, string $value, ?string $description = null): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO settings (`key`, `value`, description) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE `value` = ?, description = COALESCE(?, description), updated_at = CURRENT_TIMESTAMP
        ');
        $stmt->execute([$key, $value, $description, $value, $description]);
    }

    /**
     * Delete a setting
     */
    public function delete(string $key): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM settings WHERE `key` = ?');
        $stmt->execute([$key]);
    }
}

