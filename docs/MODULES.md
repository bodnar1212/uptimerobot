# Module Documentation

Detailed documentation for each module in the UptimeRobot application.

## Table of Contents

1. [Database Module](#database-module)
2. [Entity Modules](#entity-modules)
3. [Repository Modules](#repository-modules)
4. [Service Modules](#service-modules)
5. [HTTP Client Module](#http-client-module)
6. [Notification Modules](#notification-modules)
7. [Queue Module](#queue-module)

---

## Database Module

### Connection (`src/Database/Connection.php`)

**Purpose**: Provides a singleton PDO connection wrapper for database access.

**Design Pattern**: Singleton Pattern

**Key Features**:
- Single database connection instance
- Configuration via array or file
- Automatic error handling
- Connection reuse across requests

**Class Structure**:
```php
class Connection
{
    private static ?PDO $instance = null;
    private static array $config = [];
    
    public static function setConfig(array $config): void
    public static function getInstance(): PDO
    public static function reset(): void
}
```

**Usage Example**:
```php
// Initialize
Connection::setConfig([
    'host' => 'localhost',
    'database' => 'uptimerobot',
    'username' => 'user',
    'password' => 'pass',
]);

// Get connection
$pdo = Connection::getInstance();
$stmt = $pdo->query('SELECT * FROM monitors');
```

**Configuration Options**:
- `host`: Database hostname
- `port`: Database port (default: 3306)
- `database`: Database name
- `username`: Database username
- `password`: Database password
- `charset`: Character set (default: utf8mb4)
- `options`: PDO options array

**Error Handling**:
- Throws `RuntimeException` on connection failure
- PDO exceptions are wrapped for better error messages

---

## Entity Modules

### User Entity (`src/Entity/User.php`)

**Purpose**: Represents a user account with API key authentication.

**Properties**:
- `id` (int|null): Primary key
- `email` (string): User email (unique)
- `apiKey` (string): API key for authentication (unique, 64 chars)
- `createdAt` (DateTime): Account creation timestamp

**Key Methods**:
- `fromArray(array $data): User` - Factory method to create from database row
- `toArray(): array` - Convert entity to array for JSON serialization

**Validation**:
- Email uniqueness enforced at database level
- API key uniqueness enforced at database level

**Usage**:
```php
$user = User::fromArray([
    'id' => 1,
    'email' => 'user@example.com',
    'api_key' => 'abc123...',
    'created_at' => '2025-12-24 10:00:00'
]);

$data = $user->toArray();
```

---

### Monitor Entity (`src/Entity/Monitor.php`)

**Purpose**: Represents a URL monitor configuration.

**Properties**:
- `id` (int|null): Primary key
- `userId` (int): Owner user ID (foreign key)
- `url` (string): URL to monitor (max 2048 chars)
- `intervalSeconds` (int): Check interval (60-86400 seconds)
- `timeoutSeconds` (int): Request timeout (1-300 seconds)
- `enabled` (bool): Whether monitoring is active
- `discordWebhookUrl` (string|null): Discord webhook for notifications
- `telegramBotToken` (string|null): Telegram bot token from @BotFather
- `telegramChatId` (string|null): Telegram chat ID or username
  - **For text & voice messages:** Use numeric ID (e.g., `123456789`). Get it by messaging @userinfobot
  - **For voice calls:** Use username format (e.g., `@yourusername`). Requires CallMeBot authorization (@CallMeBot_txtbot)
- `createdAt` (DateTime): Creation timestamp
- `updatedAt` (DateTime): Last update timestamp

**Business Rules**:
- URL must be valid HTTP/HTTPS URL
- Interval must be between 60 and 86400 seconds
- Timeout must be between 1 and 300 seconds
- Enabled monitors are checked automatically

**Usage**:
```php
$monitor = new Monitor(
    userId: 1,
    url: 'https://example.com',
    intervalSeconds: 300,
    timeoutSeconds: 30,
    enabled: true,
    discordWebhookUrl: 'https://discord.com/api/webhooks/...'
);

// Update properties
$monitor->setIntervalSeconds(600);
$monitor->setEnabled(false);
```

---

### MonitorStatus Entity (`src/Entity/MonitorStatus.php`)

**Purpose**: Represents a single status check result.

**Properties**:
- `id` (int|null): Primary key
- `monitorId` (int): Associated monitor ID (foreign key)
- `status` (string): Status value ('up', 'down')
- `checkedAt` (DateTime): Check timestamp
- `responseTimeMs` (int|null): Response time in milliseconds
- `httpStatusCode` (int|null): HTTP status code (200, 404, 500, etc.)
- `errorMessage` (string|null): Error message if check failed

**Status Values**:
- `up`: Service is operational (HTTP 2xx-3xx)
- `down`: Service is down (HTTP 4xx-5xx or network error)

**Validation**:
- Status must be one of: 'up', 'down'
- Throws `InvalidArgumentException` for invalid status

**Usage**:
```php
$status = new MonitorStatus(
    monitorId: 1,
    status: 'up',
    checkedAt: new DateTime(),
    responseTimeMs: 150,
    httpStatusCode: 200,
    errorMessage: null
);

// Validate status
try {
    $status->setStatus('invalid'); // Throws InvalidArgumentException
} catch (InvalidArgumentException $e) {
    // Handle error
}
```

---

## Repository Modules

### UserRepository (`src/Repository/UserRepository.php`)

**Purpose**: Handles all database operations for users.

**Methods**:

#### `findById(int $id): ?User`
Finds a user by ID.

**Returns**: User entity or null if not found

**SQL**:
```sql
SELECT * FROM users WHERE id = ?
```

#### `findByEmail(string $email): ?User`
Finds a user by email address.

**Returns**: User entity or null if not found

**Use Case**: Check if email already exists

#### `findByApiKey(string $apiKey): ?User`
Finds a user by API key.

**Returns**: User entity or null if not found

**Use Case**: API authentication

#### `create(User $user): User`
Creates a new user.

**Returns**: User entity with ID set

**SQL**:
```sql
INSERT INTO users (email, api_key, created_at) VALUES (?, ?, ?)
```

**Note**: Generates API key if not provided

#### `update(User $user): void`
Updates an existing user.

**SQL**:
```sql
UPDATE users SET email = ?, api_key = ? WHERE id = ?
```

#### `delete(int $id): void`
Deletes a user.

**SQL**:
```sql
DELETE FROM users WHERE id = ?
```

**Note**: Cascade delete removes associated monitors

---

### MonitorRepository (`src/Repository/MonitorRepository.php`)

**Purpose**: Handles all database operations for monitors.

**Methods**:

#### `findById(int $id): ?Monitor`
Finds a monitor by ID.

**Returns**: Monitor entity or null

#### `findByUserId(int $userId): array`
Gets all monitors for a user.

**Returns**: Array of Monitor entities

**SQL**:
```sql
SELECT * FROM monitors WHERE user_id = ? ORDER BY created_at DESC
```

#### `findEnabled(): array`
Gets all enabled monitors.

**Returns**: Array of enabled Monitor entities

**Use Case**: Scheduler service fetches monitors to check

**SQL**:
```sql
SELECT * FROM monitors WHERE enabled = 1 ORDER BY id
```

#### `create(Monitor $monitor): Monitor`
Creates a new monitor.

**Returns**: Monitor entity with ID set

**SQL**:
```sql
INSERT INTO monitors (user_id, url, interval_seconds, timeout_seconds, 
                     enabled, discord_webhook_url, created_at, updated_at) 
VALUES (?, ?, ?, ?, ?, ?, ?, ?)
```

#### `update(Monitor $monitor): void`
Updates an existing monitor.

**SQL**:
```sql
UPDATE monitors SET url = ?, interval_seconds = ?, timeout_seconds = ?, 
                   enabled = ?, discord_webhook_url = ?, updated_at = ? 
WHERE id = ?
```

#### `delete(int $id): void`
Deletes a monitor.

**Note**: Cascade delete removes associated statuses and queue jobs

---

### MonitorStatusRepository (`src/Repository/MonitorStatusRepository.php`)

**Purpose**: Handles status history data access.

**Methods**:

#### `findById(int $id): ?MonitorStatus`
Finds a status record by ID.

#### `findByMonitorId(int $monitorId, int $limit = 10): array`
Gets recent status records for a monitor.

**Parameters**:
- `monitorId`: Monitor ID
- `limit`: Maximum number of records (default: 10)

**Returns**: Array of MonitorStatus entities, newest first

**SQL**:
```sql
SELECT * FROM monitor_statuses 
WHERE monitor_id = ? 
ORDER BY checked_at DESC 
LIMIT ?
```

**Use Case**: Display status history in admin panel

#### `getLatestStatus(int $monitorId): ?MonitorStatus`
Gets the most recent status for a monitor.

**Returns**: Latest MonitorStatus or null if no checks yet

**SQL**:
```sql
SELECT * FROM monitor_statuses 
WHERE monitor_id = ? 
ORDER BY checked_at DESC 
LIMIT 1
```

**Use Case**: 
- Determine current status
- Check if status changed
- Calculate next check time

#### `create(MonitorStatus $status): MonitorStatus`
Saves a new status record.

**Returns**: MonitorStatus entity with ID set

**SQL**:
```sql
INSERT INTO monitor_statuses (monitor_id, status, checked_at, 
                              response_time_ms, http_status_code, error_message) 
VALUES (?, ?, ?, ?, ?, ?)
```

---

### QueueRepository (`src/Repository/QueueRepository.php`)

**Purpose**: Manages queue job operations.

**Methods**:

#### `createJob(int $monitorId, DateTime $scheduledAt): int`
Creates a new queue job.

**Parameters**:
- `monitorId`: Monitor to check
- `scheduledAt`: When to process the job

**Returns**: Job ID

**SQL**:
```sql
INSERT INTO queue_jobs (monitor_id, scheduled_at, status, created_at) 
VALUES (?, ?, 'pending', NOW())
```

**Use Case**: Scheduler creates jobs for due monitors

#### `getPendingJobs(int $limit = 50): array`
Gets pending jobs ready to process.

**Parameters**:
- `limit`: Maximum number of jobs (default: 50)

**Returns**: Array of job data with monitor information

**SQL**:
```sql
SELECT qj.*, m.url, m.timeout_seconds, m.discord_webhook_url 
FROM queue_jobs qj
INNER JOIN monitors m ON qj.monitor_id = m.id
WHERE qj.status = 'pending' AND qj.scheduled_at <= NOW()
ORDER BY qj.scheduled_at ASC
LIMIT ?
```

**Use Case**: Worker fetches jobs to process

**Index Usage**: Uses `idx_status_scheduled` index for performance

#### `markAsProcessing(int $jobId): void`
Marks a job as being processed.

**SQL**:
```sql
UPDATE queue_jobs 
SET status = 'processing', attempts = attempts + 1 
WHERE id = ?
```

**Use Case**: Prevent multiple workers from processing same job

#### `markAsCompleted(int $jobId): void`
Marks a job as successfully completed.

**SQL**:
```sql
UPDATE queue_jobs 
SET status = 'completed', processed_at = NOW() 
WHERE id = ?
```

#### `markAsFailed(int $jobId): void`
Marks a job as failed.

**SQL**:
```sql
UPDATE queue_jobs 
SET status = 'failed', processed_at = NOW() 
WHERE id = ?
```

#### `deleteOldJobs(int $daysOld = 7): int`
Cleans up old completed/failed jobs.

**Parameters**:
- `daysOld`: Delete jobs older than this many days (default: 7)

**Returns**: Number of jobs deleted

**SQL**:
```sql
DELETE FROM queue_jobs 
WHERE status IN ('completed', 'failed') 
AND processed_at < DATE_SUB(NOW(), INTERVAL ? DAY)
```

**Use Case**: Periodic cleanup to prevent table growth

---

## Service Modules

### MonitorService (`src/Service/MonitorService.php`)

**Purpose**: Business logic for monitor CRUD operations.

**Dependencies**: MonitorRepository, QueueRepository

**Responsibilities**:
- Validate monitor configuration
- Create/update/delete monitors
- Schedule initial checks
- Enforce business rules

#### `create()`

Creates a new monitor with full validation.

**Signature**:
```php
public function create(
    int $userId,
    string $url,
    int $intervalSeconds = 300,
    int $timeoutSeconds = 30,
    bool $enabled = true,
    ?string $discordWebhookUrl = null
): Monitor
```

**Validation**:
1. URL validation:
   - Must not be empty
   - Must be valid URL format
   - Must use http:// or https:// scheme

2. Interval validation:
   - Minimum: 60 seconds
   - Maximum: 86400 seconds (24 hours)

3. Timeout validation:
   - Minimum: 1 second
   - Maximum: 300 seconds (5 minutes)

**Flow**:
1. Validate all inputs
2. Create Monitor entity
3. Save to database via repository
4. If enabled, schedule initial check
5. Return created monitor

**Errors**:
- `InvalidArgumentException`: Validation failed

**Example**:
```php
try {
    $monitor = $monitorService->create(
        userId: 1,
        url: 'https://example.com',
        intervalSeconds: 300,
        timeoutSeconds: 30,
        enabled: true
    );
} catch (InvalidArgumentException $e) {
    echo "Error: " . $e->getMessage();
}
```

#### `update()`

Updates monitor properties (partial updates supported).

**Signature**:
```php
public function update(
    int $monitorId,
    int $userId,
    ?string $url = null,
    ?int $intervalSeconds = null,
    ?int $timeoutSeconds = null,
    ?bool $enabled = null,
    ?string $discordWebhookUrl = null
): Monitor
```

**Validation**:
- Only validates fields that are being updated
- Same rules as `create()`

**Special Behavior**:
- If monitor is newly enabled, schedules a check
- Updates `updated_at` timestamp

**Flow**:
1. Load existing monitor
2. Verify ownership (user_id match)
3. Update only provided fields
4. Validate updated fields
5. Save changes
6. Schedule check if newly enabled
7. Return updated monitor

**Errors**:
- `InvalidArgumentException`: Monitor not found or validation failed

#### `delete()`

Deletes a monitor.

**Signature**:
```php
public function delete(int $monitorId, int $userId): void
```

**Validation**:
- Verifies monitor exists
- Verifies ownership

**Cascade Effects**:
- Deletes associated status records
- Deletes associated queue jobs
- Deletes associated notifications

**Errors**:
- `InvalidArgumentException`: Monitor not found or doesn't belong to user

---

### SchedulerService (`src/Service/SchedulerService.php`)

**Purpose**: Schedules monitor checks by creating queue jobs.

**Dependencies**: MonitorRepository, MonitorStatusRepository, QueueRepository

**Responsibilities**:
- Poll enabled monitors
- Determine which monitors need checking
- Create queue jobs for due checks

#### `scheduleDueChecks()`

Main method that schedules checks for all due monitors.

**Signature**:
```php
public function scheduleDueChecks(): int
```

**Returns**: Number of jobs scheduled

**Algorithm**:

```
FOR EACH enabled monitor:
    latest_status = get_latest_status(monitor_id)
    
    IF latest_status is NULL:
        // First check - schedule immediately
        schedule_check(monitor_id, NOW())
        scheduled_count++
    ELSE:
        last_check_time = latest_status.checked_at
        interval = monitor.interval_seconds
        next_check_time = last_check_time + interval
        
        IF NOW() >= next_check_time:
            // Check is due
            schedule_check(monitor_id, next_check_time)
            scheduled_count++

RETURN scheduled_count
```

**Performance**:
- Fetches all enabled monitors in one query
- Checks latest status per monitor
- Creates jobs only for due monitors
- Efficient for large numbers of monitors

**Usage**:
Run continuously via `bin/scheduler.php`:

```php
while (true) {
    $scheduled = $schedulerService->scheduleDueChecks();
    if ($scheduled > 0) {
        echo "Scheduled {$scheduled} checks\n";
    }
    sleep(30); // Configurable via SCHEDULER_INTERVAL
}
```

**Configuration**:
- Interval: 30 seconds (configurable via `SCHEDULER_INTERVAL`)

---

### WorkerService (`src/Service/WorkerService.php`)

**Purpose**: Processes queue jobs and performs HTTP checks.

**Dependencies**: 
- QueueRepository
- MonitorRepository
- MonitorStatusRepository
- HttpCheckService
- NotificationService

**Responsibilities**:
- Process pending queue jobs
- Perform concurrent HTTP checks
- Update monitor statuses
- Trigger notifications

#### `processJobs()`

Main method that processes pending jobs.

**Signature**:
```php
public function processJobs(): int
```

**Returns**: Number of jobs processed

**Algorithm**:

```
1. Fetch pending jobs (up to concurrency limit)
2. IF no jobs:
    RETURN 0

3. Mark all jobs as processing
4. Extract monitor IDs from jobs
5. Perform concurrent HTTP checks
6. Create results map (monitor_id => result)

7. FOR EACH job:
    IF monitor not found:
        mark_as_completed(job_id)
        CONTINUE
    
    result = results[monitor_id]
    IF no result:
        mark_as_failed(job_id)
        CONTINUE
    
    previous_status = get_latest_status(monitor_id)
    new_status = determine_status(result, previous_status)
    
    create_status_record(new_status)
    process_notification(monitor, new_status, previous_status)
    mark_as_completed(job_id)
    processed_count++

8. RETURN processed_count
```

**Concurrency**:
- Processes up to 50 checks simultaneously (configurable)
- Uses HttpCheckService for concurrent execution
- Non-blocking I/O via cURL multi-handle

**Error Handling**:
- Failed checks: Status marked as 'down', job marked as completed
- Missing monitors: Job marked as completed (monitor deleted)
- Exceptions: Logged, job marked as failed

**Usage**:
Run continuously via `bin/worker.php`:

```php
while (true) {
    $processed = $workerService->processJobs();
    if ($processed > 0) {
        echo "Processed {$processed} jobs\n";
    }
    sleep(1);
    
    // Cleanup every 100 iterations
    if ($iteration % 100 === 0) {
        $queueRepository->deleteOldJobs(7);
    }
}
```

**Configuration**:
- Concurrency: 50 jobs per batch (configurable via `WORKER_CONCURRENCY`)

---

### HttpCheckService (`src/Service/HttpCheckService.php`)

**Purpose**: Wraps HTTP client for monitor checks.

**Dependencies**: HttpClient, MonitorRepository

**Responsibilities**:
- Perform single monitor checks
- Perform concurrent monitor checks
- Return standardized results

#### `checkMonitor()`

Performs HTTP check for a single monitor.

**Signature**:
```php
public function checkMonitor(int $monitorId): array
```

**Returns**:
```php
[
    'monitor_id' => 1,
    'success' => true,
    'http_status_code' => 200,
    'response_time_ms' => 150,
    'error_message' => null
]
```

**Flow**:
1. Load monitor from repository
2. Call HttpClient with monitor URL and timeout
3. Return standardized result

**Errors**:
- `InvalidArgumentException`: Monitor not found

#### `checkMonitorsConcurrent()`

Performs concurrent HTTP checks for multiple monitors.

**Signature**:
```php
public function checkMonitorsConcurrent(array $monitorIds): array
```

**Parameters**:
- `monitorIds`: Array of monitor IDs to check

**Returns**: Array of result arrays

**Flow**:
1. Load all monitors from repository
2. Prepare requests array for HttpClient
3. Call HttpClient.checkConcurrent()
4. Format and return results

**Performance**:
- All checks performed concurrently
- Non-blocking I/O
- Returns when all checks complete

---

### NotificationService (`src/Service/NotificationService.php`)

**Purpose**: Handles status change detection and notification sending.

**Dependencies**: MonitorStatusRepository

**Responsibilities**:
- Detect status changes
- Determine if notification needed
- Send notifications via appropriate notifier

#### `processStatusChange()`

Processes status change and sends notification if needed.

**Signature**:
```php
public function processStatusChange(
    Monitor $monitor,
    MonitorStatus $newStatus,
    ?string $previousStatus = null
): void
```

**Parameters**:
- `monitor`: Monitor entity
- `newStatus`: New status that was just saved
- `previousStatus`: Previous status (optional, fetched if not provided)

**Notification Logic**:

```
IF previous_status is NULL:
    send_notification()  // First check - always notify
ELSE IF new_status != previous_status:
    send_notification()  // Status changed - notify
ELSE:
    skip_notification()  // No change - don't notify
```

**Flow**:
1. Get previous status if not provided
2. Check if notification should be sent
3. If yes, get webhook URL from monitor
4. Create notifier via NotificationFactory
5. Send notification
6. Log errors (don't fail on notification errors)

**Notification Types**:
- First check: Always sent
- Status change: Up → Down, Down → Up
- No change: Not sent (e.g., still down, still up)

**Error Handling**:
- Notification failures logged but don't fail the job
- Missing webhook URL: Skip notification silently

---

## HTTP Client Module

### HttpClient (`src/Http/HttpClient.php`)

**Purpose**: Performs HTTP checks using cURL with concurrent support.

**Features**:
- Single and concurrent HTTP checks
- Configurable timeout
- SSL verification
- Follow redirects
- Custom user agent

#### Constructor

```php
public function __construct(int $timeout = 30, array $options = [])
```

**Parameters**:
- `timeout`: Default timeout in seconds (default: 30)
- `options`: Additional cURL options

**Default Options**:
- `CURLOPT_FOLLOWLOCATION`: true (follow redirects)
- `CURLOPT_MAXREDIRS`: 5
- `CURLOPT_SSL_VERIFYPEER`: true
- `CURLOPT_SSL_VERIFYHOST`: 2
- `CURLOPT_USERAGENT`: 'UptimeRobot/1.0'
- `CURLOPT_RETURNTRANSFER`: true
- `CURLOPT_TIMEOUT`: timeout value
- `CURLOPT_CONNECTTIMEOUT`: 10 seconds

#### `check()`

Performs single HTTP check.

**Signature**:
```php
public function check(string $url, int $timeout = null): array
```

**Returns**:
```php
[
    'success' => true,              // HTTP 2xx-3xx
    'http_status_code' => 200,
    'response_time_ms' => 150,
    'error_message' => null
]
```

**Success Criteria**:
- HTTP status codes 200-399: Success
- HTTP status codes 400+: Failure
- Network errors: Failure

**Error Handling**:
- Network errors: Returns error_message
- Timeout errors: Returns error_message
- cURL errors: Returns error_message

#### `checkConcurrent()`

Performs multiple HTTP checks concurrently.

**Signature**:
```php
public function checkConcurrent(array $requests): array
```

**Parameters**:
```php
[
    'monitor_1' => ['url' => 'https://example.com', 'timeout' => 30, 'id' => 1],
    'monitor_2' => ['url' => 'https://google.com', 'timeout' => 30, 'id' => 2],
]
```

**Returns**: Array keyed by request key with same format as `check()`

**Implementation**:
1. Create cURL multi-handle
2. Initialize all cURL handles
3. Add handles to multi-handle
4. Execute all handles concurrently
5. Collect results
6. Clean up handles
7. Return results

**Performance**:
- Non-blocking I/O
- All requests execute simultaneously
- Returns when all complete
- Efficient for multiple checks

**Example**:
```php
$httpClient = new HttpClient();

$requests = [
    'mon1' => ['url' => 'https://example.com', 'timeout' => 30],
    'mon2' => ['url' => 'https://google.com', 'timeout' => 30],
];

$results = $httpClient->checkConcurrent($requests);
// $results['mon1'] = ['success' => true, ...]
// $results['mon2'] = ['success' => true, ...]
```

---

## Notification Modules

### NotificationInterface (`src/Notification/NotificationInterface.php`)

**Purpose**: Contract that all notifiers must implement.

**Design Pattern**: Strategy Pattern

**Interface**:
```php
interface NotificationInterface
{
    public function send(
        Monitor $monitor,
        MonitorStatus $status,
        ?string $previousStatus = null
    ): bool;
    
    public function getType(): string;
}
```

**Methods**:

#### `send()`
Sends a notification about a status change.

**Parameters**:
- `monitor`: Monitor that changed status
- `status`: New status
- `previousStatus`: Previous status (null if first check)

**Returns**: true if sent successfully, false otherwise

#### `getType()`
Returns the notification type identifier.

**Returns**: String identifier (e.g., 'discord', 'email')

---

### DiscordNotifier (`src/Notification/DiscordNotifier.php`)

**Purpose**: Sends notifications via Discord webhooks.

**Implementation**: Implements NotificationInterface

**Constructor**:
```php
public function __construct(string $webhookUrl)
```

**Features**:
- Rich embed messages
- Color-coded by status
- Includes response time, HTTP code, errors
- Timestamp included

**Message Format**:
- Title: Status-based emoji and text
- Description: Status change message
- Fields: URL, Status, Response Time, HTTP Code, Error
- Color: Green (up), Red (down)
- Timestamp: Check timestamp

**Status Colors**:
- `up`: Green (#00ff00)
- `down`: Red (#ff0000)

**Error Handling**:
- Logs errors to error_log
- Returns false on failure
- Doesn't throw exceptions

**Usage**:
```php
$notifier = new DiscordNotifier('https://discord.com/api/webhooks/...');
$success = $notifier->send($monitor, $status, 'up');
```

---

### TelegramNotifier (`src/Notification/TelegramNotifier.php`)

**Purpose**: Sends notifications via Telegram Bot API, including text messages, voice messages, and voice calls.

**Implementation**: Implements NotificationInterface

**Constructor**:
```php
public function __construct(string $botToken, string $chatId)
```

**Parameters**:
- `botToken`: Telegram bot token from @BotFather
- `chatId`: Telegram chat ID or username
  - **For text & voice messages:** Use numeric ID (e.g., `123456789`). Get it by messaging @userinfobot
  - **For voice calls:** Use username format (e.g., `@yourusername`). Requires CallMeBot authorization (@CallMeBot_txtbot)
  - **Important:** Start a conversation with your bot first by sending it a message!

**Features**:
- Text messages via Telegram Bot API
- Voice messages (audio files) via Telegram Bot API
- Voice calls via CallMeBot API (requires username format)
- Text-to-Speech (TTS) generation using `espeak` (Linux) or `say` (macOS)
- Automatic format detection (numeric ID vs username)

**Notification Sequence**:
1. Text message sent via bot
2. Voice message (audio file) sent via bot
3. Voice call via CallMeBot (if username format is used and CallMeBot is authorized)

**CallMeBot Limits**:
- **Free Tier:**
  - 50 messages per 240 minutes (4 hours)
  - Calls limited to 30 seconds
  - Requires prior authentication (message @CallMeBot_txtbot first)
- **Paid Tier ($15/month):**
  - Unlimited calls
  - Longer call duration (beyond 30 seconds)
  - No delays (personal queue)
  - Can call any recipient without prior authentication

**Chat ID Format**:
- **Numeric ID** (`123456789`): Used for text and voice messages. Most reliable format.
- **Username** (`@yourusername`): Required for voice calls via CallMeBot. Also works for text/voice messages.

**Error Handling**:
- Logs errors to error_log
- Returns false on failure
- Doesn't throw exceptions
- Gracefully skips voice call if TTS fails or CallMeBot is not authorized

**Usage**:
```php
$notifier = new TelegramNotifier('123456789:ABCdefGHIjklMNOpqrsTUVwxyz', '123456789');
$success = $notifier->send($monitor, $status, 'up');
```

---

### NotificationFactory (`src/Notification/NotificationFactory.php`)

**Purpose**: Creates notifier instances based on type.

**Design Pattern**: Factory Pattern

**Methods**:

#### `create()`
Creates a notifier instance.

**Signature**:
```php
public static function create(string $type, array $config): NotificationInterface
```

**Parameters**:
- `type`: Notification type ('discord', etc.)
- `config`: Configuration array specific to notifier type

**Returns**: NotificationInterface implementation

**Current Types**:
- `discord`: Requires `webhook_url` in config
- `telegram`: Requires `bot_token` and `chat_id` in config

**Errors**:
- `InvalidArgumentException`: Unsupported notification type

**Extensibility**:
To add a new notifier:

1. Create class implementing NotificationInterface
2. Add case to `create()` method:

```php
return match ($type) {
    'discord' => new DiscordNotifier($config['webhook_url'] ?? ''),
    'telegram' => new TelegramNotifier(
        $config['bot_token'] ?? '',
        $config['chat_id'] ?? ''
    ),
    'email' => new EmailNotifier($config['smtp_config'] ?? []),
    default => throw new \InvalidArgumentException("Unsupported type: {$type}"),
};
```

---

## Queue Module

The queue system is implemented via QueueRepository (documented above) and uses MySQL as the queue backend.

### Queue Job Lifecycle

```
1. CREATED (pending)
   ↓
2. SCHEDULED (scheduled_at <= NOW())
   ↓
3. PROCESSING (status = 'processing')
   ↓
4. COMPLETED/FAILED (status = 'completed' or 'failed')
   ↓
5. CLEANED UP (after 7 days)
```

### Queue Benefits

1. **Reliability**: Jobs persist in database
2. **Scalability**: Multiple workers can process jobs
3. **No External Dependencies**: Uses MySQL
4. **Observability**: Job status visible in database
5. **Retry Capability**: Failed jobs can be retried

### Queue Limitations

1. **Polling**: Workers poll database (not push-based)
2. **No Priority**: Jobs processed in order
3. **No Delayed Jobs**: Uses scheduled_at for timing
4. **Single Queue**: All jobs in one table

### Future Improvements

- Add job priorities
- Add delayed job support
- Add retry logic with exponential backoff
- Add job deduplication
- Consider Redis/RabbitMQ for higher throughput

---

## Module Dependencies

```
MonitorService
├── MonitorRepository
└── QueueRepository

SchedulerService
├── MonitorRepository
├── MonitorStatusRepository
└── QueueRepository

WorkerService
├── QueueRepository
├── MonitorRepository
├── MonitorStatusRepository
├── HttpCheckService
│   ├── HttpClient
│   └── MonitorRepository
└── NotificationService
    └── MonitorStatusRepository
        └── Connection

All Repositories
└── Connection
    └── Database (MySQL)
```

## Testing Modules

### Unit Testing

Each module can be tested independently:

```php
// Test MonitorService
$monitorService = new MonitorService($mockMonitorRepo, $mockQueueRepo);
$monitor = $monitorService->create(...);

// Test HttpClient
$httpClient = new HttpClient();
$result = $httpClient->check('https://example.com');
```

### Integration Testing

Test modules together:

```php
// Test full flow
$monitorService->create(...);
$schedulerService->scheduleDueChecks();
$workerService->processJobs();
```

## Performance Considerations

### Database Queries

- Use indexes on frequently queried columns
- Batch queries where possible
- Limit result sets

### HTTP Checks

- Concurrent execution reduces total time
- Non-blocking I/O prevents blocking
- Configurable concurrency limit

### Memory Usage

- Entities are lightweight
- Repositories use PDO (efficient)
- Workers process in batches

## Error Handling Patterns

### Validation Errors
- Throw `InvalidArgumentException`
- Include descriptive error messages
- Validate early

### Database Errors
- PDO exceptions wrapped
- Connection errors handled gracefully
- Transaction support if needed

### HTTP Errors
- Network errors: Return error_message
- Timeout errors: Return error_message
- Don't throw exceptions (return error array)

### Notification Errors
- Log but don't fail
- Return false on failure
- Don't block status updates

