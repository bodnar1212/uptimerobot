-- Create settings table for application configuration
CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    `key` VARCHAR(255) NOT NULL UNIQUE,
    `value` TEXT NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings
INSERT INTO settings (`key`, `value`, description) VALUES
('scheduler_interval', '30', 'Scheduler check interval in seconds (how often the scheduler runs to check for due monitors)'),
('default_monitor_interval', '60', 'Default monitor check interval in seconds (used when creating new monitors)')
ON DUPLICATE KEY UPDATE `value` = `value`;

