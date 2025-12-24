<?php

namespace UptimeRobot\Repository;

use UptimeRobot\Database\Connection;
use UptimeRobot\Entity\User;
use PDO;

class UserRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::getInstance();
    }

    public function findById(int $id): ?User
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $data = $stmt->fetch();

        return $data ? User::fromArray($data) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $data = $stmt->fetch();

        return $data ? User::fromArray($data) : null;
    }

    public function findByApiKey(string $apiKey): ?User
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE api_key = ?');
        $stmt->execute([$apiKey]);
        $data = $stmt->fetch();

        return $data ? User::fromArray($data) : null;
    }

    public function create(User $user): User
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (email, api_key, created_at) VALUES (?, ?, ?)'
        );
        $stmt->execute([
            $user->getEmail(),
            $user->getApiKey(),
            $user->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);

        $user->setId((int)$this->db->lastInsertId());
        return $user;
    }

    public function update(User $user): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET email = ?, api_key = ? WHERE id = ?'
        );
        $stmt->execute([
            $user->getEmail(),
            $user->getApiKey(),
            $user->getId(),
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
    }
}

