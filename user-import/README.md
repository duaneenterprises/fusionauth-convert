# FusionAuth User Import

A PHP-based user import system for migrating users from MySQL to FusionAuth and registering them for League Joe applications.

## Features

- **Bulk User Import**: Import users from MySQL to FusionAuth in batches
- **Application Registration**: Automatically register users for League Joe applications
- **Dry Run Mode**: Test the import process without making changes
- **Detailed Logging**: Comprehensive logging for debugging and monitoring
- **Error Tracking**: Track and report import and registration failures
- **Flexible Execution**: Run full process, import only, or registration only
- **Command Line & Web Ready**: Can be executed from CLI or triggered from web pages

## Requirements

- PHP 8.1 or higher
- MySQL/MariaDB database
- FusionAuth instance
- PDO MySQL extension

## Installation

1. Clone or copy the files to your desired location
2. Copy the environment example file:
   ```bash
   cp env.example .env
   ```
3. Edit the `.env` file with your configuration
4. Ensure the database and FusionAuth are accessible

## Configuration

Edit the `.env` file with your settings:

```bash
# Database Configuration
DB_HOST=localhost
DB_PORT=3306
DB_NAME=leaguejoe
DB_USER=root
DB_PASSWORD=your_password

# FusionAuth Configuration
FUSIONAUTH_BASE_URL=http://localhost:9011
FUSIONAUTH_API_KEY=your-api-key-here
FUSIONAUTH_TENANT_ID=2a939b29-1ae5-4695-9a6c-668b05ea64b2

# League Joe Application IDs
LEAGUE_JOE_APP_ID=45562ca3-d36c-4d0a-af6f-c0c5abfffffd

# Processing Configuration
BATCH_SIZE=100
LOG_FILE=user_import.log
LOG_LEVEL=INFO
```

## Usage

### Command Line Usage

```bash
# Run full import and registration process
php user_import.php

# Run in dry-run mode (no changes made)
php user_import.php --dry-run

# Import users only (skip registrations)
php user_import.php --import-only

# Register users only (skip imports)
php user_import.php --register-only

# Combine options
php user_import.php --dry-run --import-only
```

### Makefile Usage

```bash
# Show all available commands
make help

# Run full process
make run

# Run import only
make import

# Run registration only
make register

# Dry run - full process
make dry-run

# Dry run - import only
make dry-import

# Dry run - registration only
make dry-register

# Test connections
make test-db
make test-fa

# Show statistics
make stats

# View logs
make logs
```

### Web Integration

The script can be executed from a web page:

```php
<?php
// Load environment variables
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Include and run the import
require_once 'user_import.php';

$config = require 'config.php';
$importer = new UserImport($config);

try {
    $importer->run(false, false, false); // full process
    echo "Import completed successfully";
} catch (Exception $e) {
    echo "Import failed: " . $e->getMessage();
}
?>
```

## Process Flow

1. **User Import Phase**:
   - Fetch users from MySQL in batches
   - Check if user already exists in FusionAuth
   - Import new users to FusionAuth
   - Track import results

2. **Registration Phase**:
   - For each successfully imported user
   - Check if user is registered for each League Joe app
   - Register user for apps if not already registered
   - Track registration results

3. **Summary**:
   - Print detailed statistics
   - Report any errors encountered
   - Log all activities

## Database Schema

The script expects a `users` table with the following structure:

```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL UNIQUE,
    username VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(255),
    last_name VARCHAR(255),
    user_level INT DEFAULT 1,
    verified BOOLEAN DEFAULT FALSE,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## Role Mapping

Users are mapped to FusionAuth roles based on their `user_level`:

- Level 1 → Rookie
- Level 2 → Player  
- Level 3 → Coach
- Level 4 → Global Admin

## Logging

The system provides detailed logging with different levels:

- **DEBUG**: Detailed debugging information
- **INFO**: General information about the process
- **WARN**: Warning messages
- **ERROR**: Error messages

Logs are written to the file specified in `LOG_FILE` and also output to console when running from CLI.

## Error Handling

The system tracks and reports:

- Database connection errors
- FusionAuth API errors
- User import failures
- Registration failures
- Data mapping issues

All errors are logged with context information for debugging.

## Performance

- **Batch Processing**: Users are processed in configurable batches (default: 100)
- **No Parallelization**: Sequential processing for reliability
- **Memory Efficient**: Processes users one batch at a time
- **Resumable**: Can be restarted if interrupted

## Security

- **Environment Variables**: Sensitive configuration stored in `.env`
- **SSL Verification**: Configurable SSL verification for FusionAuth
- **Error Sanitization**: Errors are logged without exposing sensitive data
- **Dry Run Mode**: Test without making changes

## Troubleshooting

### Common Issues

1. **Database Connection Failed**:
   - Check database credentials in `.env`
   - Ensure database server is running
   - Verify network connectivity

2. **FusionAuth Connection Failed**:
   - Check FusionAuth URL and API key
   - Verify tenant ID is correct
   - Ensure FusionAuth is running

3. **User Import Failures**:
   - Check user data validity
   - Verify required fields are present
   - Check FusionAuth application roles exist

4. **Registration Failures**:
   - Verify application IDs are correct
   - Check user exists in FusionAuth
   - Ensure application roles are configured

### Debug Commands

```bash
# Test database connection
make test-db

# Test FusionAuth connection  
make test-fa

# View recent logs
make logs

# Check log file size
make log-size

# Show database statistics
make stats
```

## License

This project is part of the League Joe migration system. 