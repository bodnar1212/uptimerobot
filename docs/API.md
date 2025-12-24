# API Documentation

Complete REST API reference for UptimeRobot.

## Base URL

```
http://localhost:8000/api
```

## Authentication

All API endpoints require authentication via API key.

### Methods

**Header** (Recommended):
```
X-API-Key: your-api-key-here
```

**Query Parameter**:
```
?api_key=your-api-key-here
```

### Getting an API Key

API keys are generated when users are created. Use the admin panel or CLI tool to create users and retrieve their API keys.

## Endpoints

### Monitors

#### Create Monitor

Create a new monitor for a URL.

**Endpoint**: `POST /api/monitors`

**Headers**:
```
X-API-Key: your-api-key
Content-Type: application/json
```

**Request Body**:
```json
{
    "url": "https://example.com",
    "interval_seconds": 300,
    "timeout_seconds": 30,
    "enabled": true,
    "discord_webhook_url": "https://discord.com/api/webhooks/..."
}
```

**Parameters**:
- `url` (string, required): URL to monitor (must be HTTP or HTTPS)
- `interval_seconds` (integer, optional): Check interval in seconds (60-86400, default: 300)
- `timeout_seconds` (integer, optional): Request timeout in seconds (1-300, default: 30)
- `enabled` (boolean, optional): Whether monitoring is enabled (default: true)
- `discord_webhook_url` (string, optional): Discord webhook URL for notifications

**Response** (201 Created):
```json
{
    "monitor": {
        "id": 1,
        "user_id": 1,
        "url": "https://example.com",
        "interval_seconds": 300,
        "timeout_seconds": 30,
        "enabled": true,
        "discord_webhook_url": "https://discord.com/api/webhooks/...",
        "created_at": "2025-12-24 10:00:00",
        "updated_at": "2025-12-24 10:00:00"
    }
}
```

**Error Responses**:
- `400 Bad Request`: Invalid input (e.g., invalid URL, interval out of range)
- `401 Unauthorized`: Invalid or missing API key

**Example**:
```bash
curl -X POST http://localhost:8000/api/monitors \
  -H "X-API-Key: your-api-key" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://example.com",
    "interval_seconds": 300,
    "timeout_seconds": 30,
    "enabled": true
  }'
```

---

#### List Monitors

Get all monitors for the authenticated user.

**Endpoint**: `GET /api/monitors`

**Headers**:
```
X-API-Key: your-api-key
```

**Response** (200 OK):
```json
{
    "monitors": [
        {
            "id": 1,
            "user_id": 1,
            "url": "https://example.com",
            "interval_seconds": 300,
            "timeout_seconds": 30,
            "enabled": true,
            "discord_webhook_url": null,
            "created_at": "2025-12-24 10:00:00",
            "updated_at": "2025-12-24 10:00:00"
        },
        {
            "id": 2,
            "user_id": 1,
            "url": "https://google.com",
            "interval_seconds": 600,
            "timeout_seconds": 30,
            "enabled": false,
            "discord_webhook_url": null,
            "created_at": "2025-12-24 11:00:00",
            "updated_at": "2025-12-24 11:00:00"
        }
    ]
}
```

**Example**:
```bash
curl http://localhost:8000/api/monitors?api_key=your-api-key
```

---

#### Get Monitor

Get a specific monitor by ID.

**Endpoint**: `GET /api/monitors/{id}`

**Headers**:
```
X-API-Key: your-api-key
```

**Path Parameters**:
- `id` (integer, required): Monitor ID

**Response** (200 OK):
```json
{
    "monitor": {
        "id": 1,
        "user_id": 1,
        "url": "https://example.com",
        "interval_seconds": 300,
        "timeout_seconds": 30,
        "enabled": true,
        "discord_webhook_url": null,
        "created_at": "2025-12-24 10:00:00",
        "updated_at": "2025-12-24 10:00:00",
        "recent_statuses": [
            {
                "id": 100,
                "monitor_id": 1,
                "status": "up",
                "checked_at": "2025-12-24 10:05:00",
                "response_time_ms": 150,
                "http_status_code": 200,
                "error_message": null
            },
            {
                "id": 99,
                "monitor_id": 1,
                "status": "up",
                "checked_at": "2025-12-24 10:00:00",
                "response_time_ms": 145,
                "http_status_code": 200,
                "error_message": null
            }
        ]
    }
}
```

**Error Responses**:
- `404 Not Found`: Monitor not found or doesn't belong to user
- `401 Unauthorized`: Invalid or missing API key

**Example**:
```bash
curl http://localhost:8000/api/monitors/1?api_key=your-api-key
```

---

#### Update Monitor

Update a monitor's configuration.

**Endpoint**: `PUT /api/monitors/{id}`

**Headers**:
```
X-API-Key: your-api-key
Content-Type: application/json
```

**Path Parameters**:
- `id` (integer, required): Monitor ID

**Request Body** (all fields optional):
```json
{
    "url": "https://newexample.com",
    "interval_seconds": 600,
    "timeout_seconds": 45,
    "enabled": false,
    "discord_webhook_url": null
}
```

**Parameters**:
- `url` (string, optional): New URL (must be HTTP or HTTPS)
- `interval_seconds` (integer, optional): New interval (60-86400)
- `timeout_seconds` (integer, optional): New timeout (1-300)
- `enabled` (boolean, optional): Enable/disable monitoring
- `discord_webhook_url` (string, optional): New webhook URL (set to null to remove)

**Response** (200 OK):
```json
{
    "monitor": {
        "id": 1,
        "user_id": 1,
        "url": "https://newexample.com",
        "interval_seconds": 600,
        "timeout_seconds": 45,
        "enabled": false,
        "discord_webhook_url": null,
        "created_at": "2025-12-24 10:00:00",
        "updated_at": "2025-12-24 10:30:00"
    }
}
```

**Error Responses**:
- `400 Bad Request`: Invalid input
- `404 Not Found`: Monitor not found or doesn't belong to user
- `401 Unauthorized`: Invalid or missing API key

**Example**:
```bash
curl -X PUT http://localhost:8000/api/monitors/1 \
  -H "X-API-Key: your-api-key" \
  -H "Content-Type: application/json" \
  -d '{
    "interval_seconds": 600,
    "enabled": false
  }'
```

---

#### Delete Monitor

Delete a monitor.

**Endpoint**: `DELETE /api/monitors/{id}`

**Headers**:
```
X-API-Key: your-api-key
```

**Path Parameters**:
- `id` (integer, required): Monitor ID

**Response** (200 OK):
```json
{
    "message": "Monitor deleted successfully"
}
```

**Error Responses**:
- `404 Not Found`: Monitor not found or doesn't belong to user
- `401 Unauthorized`: Invalid or missing API key

**Example**:
```bash
curl -X DELETE http://localhost:8000/api/monitors/1?api_key=your-api-key
```

---

## Error Handling

### Error Response Format

All errors follow this format:

```json
{
    "error": "Error message here"
}
```

### HTTP Status Codes

- `200 OK`: Request successful
- `201 Created`: Resource created successfully
- `400 Bad Request`: Invalid request data
- `401 Unauthorized`: Authentication failed
- `404 Not Found`: Resource not found
- `405 Method Not Allowed`: HTTP method not allowed

### Common Errors

#### Invalid API Key
```json
{
    "error": "Unauthorized: Invalid or missing API key"
}
```

#### Validation Error
```json
{
    "error": "URL must use http or https scheme"
}
```

#### Not Found
```json
{
    "error": "Monitor not found"
}
```

## Rate Limiting

Currently, there is no rate limiting implemented. Consider implementing rate limiting for production use.

## CORS

CORS is enabled for all origins. For production, restrict to specific domains:

```php
header('Access-Control-Allow-Origin: https://yourdomain.com');
```

## Best Practices

1. **Store API keys securely**: Never commit API keys to version control
2. **Use HTTPS**: Always use HTTPS in production
3. **Handle errors**: Check HTTP status codes and error messages
4. **Validate input**: Validate URLs and parameters before sending
5. **Monitor usage**: Track API usage and monitor for abuse
6. **Use appropriate intervals**: Don't set intervals too low (minimum 60 seconds)
7. **Set reasonable timeouts**: Timeouts should be less than interval

## SDK Examples

### PHP

```php
<?php

class UptimeRobotClient {
    private $apiKey;
    private $baseUrl;
    
    public function __construct($apiKey, $baseUrl = 'http://localhost:8000') {
        $this->apiKey = $apiKey;
        $this->baseUrl = $baseUrl;
    }
    
    private function request($method, $endpoint, $data = null) {
        $ch = curl_init($this->baseUrl . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'X-API-Key: ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_CUSTOMREQUEST => $method,
        ]);
        
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'code' => $httpCode,
            'data' => json_decode($response, true),
        ];
    }
    
    public function createMonitor($url, $intervalSeconds = 300, $timeoutSeconds = 30) {
        return $this->request('POST', '/api/monitors', [
            'url' => $url,
            'interval_seconds' => $intervalSeconds,
            'timeout_seconds' => $timeoutSeconds,
        ]);
    }
    
    public function listMonitors() {
        return $this->request('GET', '/api/monitors');
    }
    
    public function getMonitor($id) {
        return $this->request('GET', "/api/monitors/{$id}");
    }
    
    public function updateMonitor($id, $data) {
        return $this->request('PUT', "/api/monitors/{$id}", $data);
    }
    
    public function deleteMonitor($id) {
        return $this->request('DELETE', "/api/monitors/{$id}");
    }
}

// Usage
$client = new UptimeRobotClient('your-api-key');
$result = $client->createMonitor('https://example.com', 300, 30);
```

### Python

```python
import requests

class UptimeRobotClient:
    def __init__(self, api_key, base_url='http://localhost:8000'):
        self.api_key = api_key
        self.base_url = base_url
        self.headers = {
            'X-API-Key': api_key,
            'Content-Type': 'application/json',
        }
    
    def create_monitor(self, url, interval_seconds=300, timeout_seconds=30):
        response = requests.post(
            f'{self.base_url}/api/monitors',
            headers=self.headers,
            json={
                'url': url,
                'interval_seconds': interval_seconds,
                'timeout_seconds': timeout_seconds,
            }
        )
        return response.json()
    
    def list_monitors(self):
        response = requests.get(
            f'{self.base_url}/api/monitors',
            headers=self.headers
        )
        return response.json()
    
    def get_monitor(self, monitor_id):
        response = requests.get(
            f'{self.base_url}/api/monitors/{monitor_id}',
            headers=self.headers
        )
        return response.json()
    
    def update_monitor(self, monitor_id, **kwargs):
        response = requests.put(
            f'{self.base_url}/api/monitors/{monitor_id}',
            headers=self.headers,
            json=kwargs
        )
        return response.json()
    
    def delete_monitor(self, monitor_id):
        response = requests.delete(
            f'{self.base_url}/api/monitors/{monitor_id}',
            headers=self.headers
        )
        return response.json()

# Usage
client = UptimeRobotClient('your-api-key')
result = client.create_monitor('https://example.com', 300, 30)
```

### JavaScript/Node.js

```javascript
class UptimeRobotClient {
    constructor(apiKey, baseUrl = 'http://localhost:8000') {
        this.apiKey = apiKey;
        this.baseUrl = baseUrl;
    }
    
    async request(method, endpoint, data = null) {
        const options = {
            method,
            headers: {
                'X-API-Key': this.apiKey,
                'Content-Type': 'application/json',
            },
        };
        
        if (data) {
            options.body = JSON.stringify(data);
        }
        
        const response = await fetch(`${this.baseUrl}${endpoint}`, options);
        return await response.json();
    }
    
    async createMonitor(url, intervalSeconds = 300, timeoutSeconds = 30) {
        return this.request('POST', '/api/monitors', {
            url,
            interval_seconds: intervalSeconds,
            timeout_seconds: timeoutSeconds,
        });
    }
    
    async listMonitors() {
        return this.request('GET', '/api/monitors');
    }
    
    async getMonitor(id) {
        return this.request('GET', `/api/monitors/${id}`);
    }
    
    async updateMonitor(id, data) {
        return this.request('PUT', `/api/monitors/${id}`, data);
    }
    
    async deleteMonitor(id) {
        return this.request('DELETE', `/api/monitors/${id}`);
    }
}

// Usage
const client = new UptimeRobotClient('your-api-key');
const result = await client.createMonitor('https://example.com', 300, 30);
```

