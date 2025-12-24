<?php

namespace UptimeRobot\Repository;

use UptimeRobot\Database\Connection;
use PDO;

class QueueRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::getInstance();
    }

    public function createJob(int $monitorId, \DateTime $scheduledAt): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO queue_jobs (monitor_id, scheduled_at, status, created_at) VALUES (?, ?, ?, NOW())'
        );
        $stmt->execute([
            $monitorId,
            $scheduledAt->format('Y-m-d H:i:s'),
            'pending',
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function getPendingJobs(int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            'SELECT qj.*, m.url, m.timeout_seconds 
             FROM queue_jobs qj
             INNER JOIN monitors m ON qj.monitor_id = m.id
             WHERE qj.status = ? AND qj.scheduled_at <= NOW()
             ORDER BY qj.scheduled_at ASC
             LIMIT ?'
        );
        $stmt->execute(['pending', $limit]);
        return $stmt->fetchAll();
    }

    public function markAsProcessing(int $jobId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE queue_jobs SET status = ?, attempts = attempts + 1 WHERE id = ?'
        );
        $stmt->execute(['processing', $jobId]);
    }

    public function markAsCompleted(int $jobId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE queue_jobs SET status = ?, processed_at = NOW() WHERE id = ?'
        );
        $stmt->execute(['completed', $jobId]);
    }

    public function markAsFailed(int $jobId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE queue_jobs SET status = ?, processed_at = NOW() WHERE id = ?'
        );
        $stmt->execute(['failed', $jobId]);
    }

    public function deleteOldJobs(int $daysOld = 7): int
    {
        $stmt = $this->db->prepare(
            'DELETE FROM queue_jobs WHERE status IN (?, ?) AND processed_at < DATE_SUB(NOW(), INTERVAL ? DAY)'
        );
        $stmt->execute(['completed', 'failed', $daysOld]);
        return $stmt->rowCount();
    }
}

