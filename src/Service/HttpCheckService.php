<?php

namespace UptimeRobot\Service;

use UptimeRobot\Http\HttpClient;
use UptimeRobot\Repository\MonitorRepository;

class HttpCheckService
{
    private HttpClient $httpClient;
    private MonitorRepository $monitorRepository;

    public function __construct(HttpClient $httpClient, MonitorRepository $monitorRepository)
    {
        $this->httpClient = $httpClient;
        $this->monitorRepository = $monitorRepository;
    }

    /**
     * Perform HTTP check for a single monitor
     */
    public function checkMonitor(int $monitorId): array
    {
        $monitor = $this->monitorRepository->findById($monitorId);

        if (!$monitor) {
            throw new \InvalidArgumentException("Monitor not found: {$monitorId}");
        }

        $result = $this->httpClient->check($monitor->getUrl(), $monitor->getTimeoutSeconds());

        return [
            'monitor_id' => $monitorId,
            'success' => $result['success'],
            'http_status_code' => $result['http_status_code'],
            'response_time_ms' => $result['response_time_ms'],
            'error_message' => $result['error_message'],
        ];
    }

    /**
     * Perform HTTP checks for multiple monitors concurrently
     * 
     * @param array $monitorIds Array of monitor IDs
     * @return array Array of check results
     */
    public function checkMonitorsConcurrent(array $monitorIds): array
    {
        if (empty($monitorIds)) {
            return [];
        }

        $monitors = [];
        foreach ($monitorIds as $monitorId) {
            $monitor = $this->monitorRepository->findById($monitorId);
            if ($monitor) {
                $monitors[$monitorId] = $monitor;
            }
        }

        if (empty($monitors)) {
            return [];
        }

        // Prepare requests for concurrent checking
        $requests = [];
        foreach ($monitors as $monitorId => $monitor) {
            $requests[$monitorId] = [
                'url' => $monitor->getUrl(),
                'timeout' => $monitor->getTimeoutSeconds(),
                'id' => $monitorId,
            ];
        }

        // Perform concurrent checks
        $results = $this->httpClient->checkConcurrent($requests);

        // Format results
        $formattedResults = [];
        foreach ($results as $monitorId => $result) {
            $formattedResults[] = [
                'monitor_id' => $monitorId,
                'success' => $result['success'],
                'http_status_code' => $result['http_status_code'],
                'response_time_ms' => $result['response_time_ms'],
                'error_message' => $result['error_message'],
            ];
        }

        return $formattedResults;
    }
}

