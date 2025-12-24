# Architecture Documentation

## System Architecture Overview

UptimeRobot follows a clean, layered architecture with clear separation of concerns. This document provides a detailed explanation of the system design and data flow.

## High-Level Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Client Layer                              │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │   REST API   │  │  Admin Panel │  │ Status Page  │     │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘     │
└─────────┼──────────────────┼──────────────────┼────────────┘
          │                  │                  │
┌─────────▼──────────────────▼──────────────────▼────────────┐
│                    Service Layer                            │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │ MonitorService│ │WorkerService │ │SchedulerService│     │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘     │
│         │                 │                  │             │
│  ┌──────▼─────────────────▼──────────────────▼───────┐     │
│  │         HttpCheckService | NotificationService   │     │
│  └───────────────────────────────────────────────────┘     │
└─────────┬──────────────────┬──────────────────┬────────────┘
          │                  │                  │
┌─────────▼──────────────────▼──────────────────▼────────────┐
│                  Repository Layer                          │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │MonitorRepo   │  │QueueRepo     │  │StatusRepo    │     │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘     │
└─────────┼──────────────────┼──────────────────┼────────────┘
          │                  │                  │
┌─────────▼──────────────────▼──────────────────▼────────────┐
│                    Entity Layer                             │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │   Monitor    │  │ MonitorStatus│  │    User     │     │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘     │
└─────────┼──────────────────┼──────────────────┼────────────┘
          │                  │                  │
┌─────────▼──────────────────▼──────────────────▼────────────┐
│                  Database Layer                              │
│                    MySQL Database                            │
└─────────────────────────────────────────────────────────────┘
```

## Component Details

### 1. Client Layer

#### REST API (`public/index.php`)
- Handles HTTP requests
- Routes to appropriate handlers
- Returns JSON responses
- Authenticates via API keys

#### Admin Panel (`public/admin.php`)
- Web interface for managing monitors
- CRUD operations for monitors
- View statistics and status history
- No authentication (admin access)

#### Status Page (`public/status.php`)
- Public-facing status page
- Shows service availability
- Displays uptime statistics
- No authentication required

### 2. Service Layer

#### MonitorService
**Responsibilities**:
- Validate monitor configuration
- Create/update/delete monitors
- Schedule initial checks
- Enforce business rules

**Dependencies**: MonitorRepository, QueueRepository

#### SchedulerService
**Responsibilities**:
- Poll enabled monitors
- Determine which monitors need checking
- Create queue jobs for due checks
- Run periodically (every 30 seconds)

**Dependencies**: MonitorRepository, MonitorStatusRepository, QueueRepository

**Algorithm**:
```
FOR EACH enabled monitor:
    latest_status = get_latest_status(monitor_id)
    IF latest_status is NULL:
        schedule_check(monitor_id, NOW())
    ELSE:
        last_check_time = latest_status.checked_at
        next_check_time = last_check_time + monitor.interval_seconds
        IF NOW() >= next_check_time:
            schedule_check(monitor_id, next_check_time)
```

#### WorkerService
**Responsibilities**:
- Process queue jobs
- Perform HTTP checks concurrently
- Update monitor statuses
- Trigger notifications
- Handle errors gracefully

**Dependencies**: QueueRepository, MonitorRepository, MonitorStatusRepository, HttpCheckService, NotificationService

**Algorithm**:
```
WHILE true:
    jobs = get_pending_jobs(limit=50)
    IF jobs is empty:
        sleep(1)
        CONTINUE
    
    FOR EACH job:
        mark_as_processing(job.id)
    
    monitor_ids = extract_monitor_ids(jobs)
    results = check_monitors_concurrent(monitor_ids)
    
    FOR EACH job:
        result = results[job.monitor_id]
        IF result is NULL:
            mark_as_failed(job.id)
            CONTINUE
        
        previous_status = get_latest_status(job.monitor_id)
        new_status = create_status(result)
        
        IF status_changed(previous_status, new_status):
            send_notification(monitor, new_status, previous_status)
        
        mark_as_completed(job.id)
    
    sleep(1)
```

#### HttpCheckService
**Responsibilities**:
- Wrap HTTP client for monitor checks
- Handle single and concurrent checks
- Return standardized results

**Dependencies**: HttpClient, MonitorRepository

#### NotificationService
**Responsibilities**:
- Detect status changes
- Determine if notification needed
- Create appropriate notifier
- Send notifications

**Dependencies**: MonitorStatusRepository

**Notification Logic**:
```
IF previous_status is NULL:
    send_notification()  // First check
ELSE IF new_status != previous_status:
    send_notification()  // Status changed
ELSE:
    skip_notification()  // No change
```

### 3. Repository Layer

All repositories follow the same pattern:
- Use PDO for database access
- Return Entity objects or arrays
- Handle SQL queries
- No business logic

### 4. Entity Layer

Entities are plain PHP objects with:
- Properties (private)
- Getters and setters
- `fromArray()` and `toArray()` methods
- No database logic

### 5. Database Layer

- Singleton PDO connection
- Configuration via environment variables
- Connection pooling
- Error handling

## Data Flow

### Monitor Creation Flow

```
1. API receives POST /api/monitors
2. MonitorService.create() validates input
3. MonitorService creates Monitor entity
4. MonitorRepository.save() inserts into database
5. MonitorService schedules initial check
6. QueueRepository.createJob() adds to queue
7. Response returned to client
```

### Status Check Flow

```
1. SchedulerService runs every 30 seconds
2. Checks which monitors are due
3. Creates queue jobs for due monitors
4. WorkerService polls queue
5. Gets pending jobs (up to 50)
6. HttpCheckService performs concurrent checks
7. Results processed and statuses created
8. NotificationService checks for changes
9. Notifications sent if status changed
10. Jobs marked as completed
```

### Notification Flow

```
1. WorkerService detects status change
2. NotificationService.processStatusChange() called
3. Gets previous status from database
4. Compares with new status
5. If changed, NotificationFactory creates notifier
6. Notifier.send() sends notification
7. Result logged
```

## Concurrency Model

### HTTP Checks

- Uses cURL multi-handle
- Non-blocking I/O
- Processes up to 50 checks simultaneously
- Configurable via `WORKER_CONCURRENCY`

### Queue Processing

- Single worker process
- Processes jobs in batches
- Marks jobs as processing to prevent duplicates
- Multiple workers can run (each processes different jobs)

### Database Access

- PDO with prepared statements
- Connection pooling via singleton
- No explicit locking (rely on MySQL transactions)
- Indexes optimize query performance

## Scalability Considerations

### Horizontal Scaling

- Multiple worker instances can run
- Each worker processes different jobs
- Database queue prevents duplicate processing
- Stateless workers (no shared state)

### Vertical Scaling

- Increase `WORKER_CONCURRENCY` for more concurrent checks
- Adjust `SCHEDULER_INTERVAL` for more frequent scheduling
- Database indexes optimize queries
- Connection pooling reduces overhead

### Performance Optimizations

- Concurrent HTTP checks (non-blocking)
- Database indexes on frequently queried columns
- Batch processing of queue jobs
- Efficient status history queries

## Error Handling

### HTTP Check Errors

- Network errors: Logged, status marked as 'down'
- Timeout errors: Logged, status marked as 'down'
- HTTP errors (4xx, 5xx): Status marked as 'down'
- All errors stored in `error_message` field

### Queue Job Errors

- Failed jobs marked as 'failed'
- No automatic retry (can be added)
- Old failed jobs cleaned up after 7 days

### Notification Errors

- Errors logged but don't fail job
- Notification status stored in `notifications` table
- Failed notifications don't block status updates

## Security Considerations

### API Authentication

- API keys stored in database
- Validated on every request
- No session management (stateless)

### Input Validation

- URL validation (must be HTTP/HTTPS)
- Interval/timeout bounds checking
- SQL injection prevention (prepared statements)
- XSS prevention (htmlspecialchars in views)

### Database Security

- Prepared statements prevent SQL injection
- Foreign key constraints maintain data integrity
- Cascade deletes clean up related records

## Monitoring and Observability

### Logging

- Worker logs: Processed job counts
- Scheduler logs: Scheduled job counts
- Error logs: PHP error_log for exceptions

### Metrics Available

- Total monitors
- Enabled monitors
- Pending/processing jobs
- Up/down status counts
- Uptime percentages

### Health Checks

- Database connection health
- Worker process status
- Scheduler process status
- Queue job processing rate

## Future Enhancements

### Potential Improvements

1. **Retry Logic**: Automatic retry for failed checks
2. **Rate Limiting**: Prevent too many checks per domain
3. **Metrics Export**: Prometheus/StatsD integration
4. **Webhook Notifications**: Generic webhook support
5. **Email Notifications**: SMTP-based notifications
6. **SMS Notifications**: Twilio integration
7. **Status Page Customization**: Custom branding
8. **Multi-region Checks**: Check from multiple locations
9. **SSL Certificate Monitoring**: Expiry date tracking
10. **Performance Monitoring**: Response time percentiles

### Architecture Evolution

- **Microservices**: Split into separate services
- **Message Queue**: Replace database queue with RabbitMQ/Redis
- **Caching Layer**: Redis for frequently accessed data
- **Load Balancer**: Distribute requests across workers
- **API Gateway**: Centralized API management

