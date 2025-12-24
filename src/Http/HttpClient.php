<?php

namespace UptimeRobot\Http;

class HttpClient
{
    private int $timeout;
    private array $options;

    public function __construct(int $timeout = 30, array $options = [])
    {
        $this->timeout = $timeout;
        $this->options = array_merge([
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'UptimeRobot/1.0',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_NOBODY => false,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
        ], $options);
    }

    /**
     * Perform a single HTTP check
     */
    public function check(string $url, int $timeout = null): array
    {
        $timeout = $timeout ?? $this->timeout;
        $startTime = microtime(true);

        $ch = curl_init($url);
        $options = $this->options;
        $options[CURLOPT_TIMEOUT] = $timeout;
        
        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        
        curl_close($ch);

        $responseTime = (int)((microtime(true) - $startTime) * 1000);

        if ($errno !== CURLE_OK || $error) {
            return [
                'success' => false,
                'http_status_code' => null,
                'response_time_ms' => $responseTime,
                'error_message' => $error ?: 'Unknown error',
            ];
        }

        // Consider HTTP 2xx and 3xx as success
        $isSuccess = $httpCode >= 200 && $httpCode < 400;

        return [
            'success' => $isSuccess,
            'http_status_code' => $httpCode,
            'response_time_ms' => $responseTime,
            'error_message' => $isSuccess ? null : "HTTP {$httpCode}",
        ];
    }

    /**
     * Perform multiple HTTP checks concurrently using cURL multi-handle
     * 
     * @param array $requests Array of ['url' => string, 'timeout' => int, 'id' => mixed]
     * @return array Array of results with same keys as input
     */
    public function checkConcurrent(array $requests): array
    {
        if (empty($requests)) {
            return [];
        }

        $multiHandle = curl_multi_init();
        $handles = [];
        $results = [];

        // Initialize all curl handles
        foreach ($requests as $key => $request) {
            $url = $request['url'];
            $timeout = $request['timeout'] ?? $this->timeout;
            
            $ch = curl_init($url);
            if ($ch === false) {
                throw new \RuntimeException("Failed to initialize cURL handle for URL: {$url}");
            }
            
            // Set options individually to avoid issues with invalid options
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_USERAGENT, 'UptimeRobot/1.0');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_NOBODY, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            
            curl_multi_add_handle($multiHandle, $ch);
            
            $handles[$key] = [
                'handle' => $ch,
                'start_time' => microtime(true),
                'timeout' => $timeout,
            ];
        }

        // Execute all handles concurrently
        $running = null;
        do {
            $mrc = curl_multi_exec($multiHandle, $running);
            if ($running > 0) {
                curl_multi_select($multiHandle, 0.1);
            }
        } while ($running > 0);

        // Collect results
        foreach ($handles as $key => $data) {
            $ch = $data['handle'];
            $startTime = $data['start_time'];
            
            $response = curl_multi_getcontent($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            
            $responseTime = (int)((microtime(true) - $startTime) * 1000);

            if ($errno !== CURLE_OK || $error) {
                $results[$key] = [
                    'success' => false,
                    'http_status_code' => null,
                    'response_time_ms' => $responseTime,
                    'error_message' => $error ?: 'Unknown error',
                ];
            } else {
                $isSuccess = $httpCode >= 200 && $httpCode < 400;
                $results[$key] = [
                    'success' => $isSuccess,
                    'http_status_code' => $httpCode,
                    'response_time_ms' => $responseTime,
                    'error_message' => $isSuccess ? null : "HTTP {$httpCode}",
                ];
            }

            curl_multi_remove_handle($multiHandle, $ch);
            curl_close($ch);
        }

        curl_multi_close($multiHandle);

        return $results;
    }
}

