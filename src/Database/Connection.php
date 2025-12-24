<?php

namespace UptimeRobot\Database;

use PDO;
use PDOException;

class Connection
{
    private static ?PDO $instance = null;
    private static array $config = [];

    private function __construct()
    {
        // Private constructor to prevent instantiation
    }

    public static function setConfig(array $config): void
    {
        self::$config = $config;
    }

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::$instance = self::createConnection();
        }

        return self::$instance;
    }

    private static function createConnection(): PDO
    {
        if (empty(self::$config)) {
            self::$config = require __DIR__ . '/../../config/database.php';
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            self::$config['host'],
            self::$config['port'],
            self::$config['database'],
            self::$config['charset']
        );

        try {
            $pdo = new PDO(
                $dsn,
                self::$config['username'],
                self::$config['password'],
                self::$config['options']
            );
            return $pdo;
        } catch (PDOException $e) {
            throw new \RuntimeException(
                "Database connection failed: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    public static function reset(): void
    {
        self::$instance = null;
    }
}

