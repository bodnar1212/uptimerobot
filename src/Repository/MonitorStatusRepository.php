<?php

namespace UptimeRobot\Repository;

use UptimeRobot\Database\Connection;
use UptimeRobot\Entity\MonitorStatus;
use PDO;

class MonitorStatusRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::getInstance();
    }

    public function findById(int $id): ?MonitorStatus
    {
        $stmt = $this->db->prepare('SELECT * FROM monitor_statuses WHERE id = ?');
        $stmt->execute([$id]);
        $data = $stmt->fetch();

        return $data ? MonitorStatus::fromArray($data) : null;
    }

    public function findByMonitorId(int $monitorId, int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM monitor_statuses WHERE monitor_id = ? ORDER BY checked_at DESC LIMIT ?'
        );
        $stmt->execute([$monitorId, $limit]);
        $data = $stmt->fetchAll();

        return array_map(fn($row) => MonitorStatus::fromArray($row), $data);
    }

    public function getLatestStatus(int $monitorId): ?MonitorStatus
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM monitor_statuses WHERE monitor_id = ? ORDER BY checked_at DESC LIMIT 1'
        );
        $stmt->execute([$monitorId]);
        $data = $stmt->fetch();

        return $data ? MonitorStatus::fromArray($data) : null;
    }

    public function create(MonitorStatus $status): MonitorStatus
    {
        $stmt = $this->db->prepare(
            'INSERT INTO monitor_statuses (monitor_id, status, checked_at, response_time_ms, http_status_code, error_message) 
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $status->getMonitorId(),
            $status->getStatus(),
            $status->getCheckedAt()->format('Y-m-d H:i:s'),
            $status->getResponseTimeMs(),
            $status->getHttpStatusCode(),
            $status->getErrorMessage(),
        ]);

        $status->setId((int)$this->db->lastInsertId());
        return $status;
    }
}

