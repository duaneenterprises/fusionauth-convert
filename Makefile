.PHONY: build build-no-cache up down clean help run import register dry-run dry-import dry-register test-db test-fa test-setup stats clean-logs setup logs log-size

# Build and start services using local docker-compose configuration
build:
	docker-compose -f docker/docker-compose-local.yml up --build -d

# Build and start services with no-cache flag
build-no-cache:
	docker-compose -f docker/docker-compose-local.yml build --no-cache && docker-compose -f docker/docker-compose-local.yml up -d

# Start services (without rebuilding)
up:
	docker-compose -f docker/docker-compose-local.yml up -d

# Stop and remove services
down:
	docker-compose -f docker/docker-compose-local.yml down

# Stop, remove services and clean up volumes
clean:
	docker-compose -f docker/docker-compose-local.yml down -v

# =============================================================================
# User Import Targets
# =============================================================================

# Default target
help:
	@echo "FusionAuth Convert - Available targets:"
	@echo ""
	@echo "Docker Management:"
	@echo "  build            - Build and start services"
	@echo "  build-no-cache   - Build with no-cache and start services"
	@echo "  up               - Start services (without rebuilding)"
	@echo "  down             - Stop and remove services"
	@echo "  clean            - Stop, remove services and clean up volumes"
	@echo ""
	@echo "User Import:"
	@echo "  run              - Run full import and registration process"
	@echo "  import           - Run only user import"
	@echo "  register         - Run only user registration"
	@echo "  dry-run          - Run full process in dry-run mode"
	@echo "  dry-import       - Run import in dry-run mode"
	@echo "  dry-register     - Run registration in dry-run mode"
	@echo "  test-db          - Test database connection"
	@echo "  test-fa          - Test FusionAuth connection"
	@echo "  test-setup       - Test complete setup"
	@echo "  stats            - Show database statistics"
	@echo "  clean-logs       - Clean log files"
	@echo "  setup            - Setup environment file"
	@echo "  logs             - Show last log entries"
	@echo "  log-size         - Show log file size"

# Full process
run:
	@echo "Running full user import and registration process..."
	cd user-import && php user_import.php

# Import only
import:
	@echo "Running user import only..."
	cd user-import && php user_import.php --import-only

# Register only
register:
	@echo "Running user registration only..."
	cd user-import && php user_import.php --register-only

# Dry run - full process
dry-run:
	@echo "Running full process in dry-run mode..."
	cd user-import && php user_import.php --dry-run

# Dry run - import only
dry-import:
	@echo "Running import in dry-run mode..."
	cd user-import && php user_import.php --dry-run --import-only

# Dry run - register only
dry-register:
	@echo "Running registration in dry-run mode..."
	cd user-import && php user_import.php --dry-run --register-only

# Test database connection
test-db:
	@echo "Testing database connection..."
	@cd user-import && php -r 'require_once "config.php"; require_once "DatabaseClient.php"; $$config = require "config.php"; $$db = new DatabaseClient($$config["database"]); if ($$db->testConnection()) { echo "✅ Database connection successful\n"; $$stats = $$db->getStats(); echo "📊 Database statistics:\n"; echo "   Total users: " . $$stats["total_users"] . "\n"; echo "   Confirmed users: " . $$stats["confirmed_users"] . "\n"; echo "   Unconfirmed users: " . $$stats["unconfirmed_users"] . "\n"; echo "   Users by level: " . json_encode($$stats["users_by_level"]) . "\n"; } else { echo "❌ Database connection failed\n"; exit(1); }'

# Test FusionAuth connection
test-fa:
	@echo "Testing FusionAuth connection..."
	@cd user-import && php -r "
	require_once 'config.php';
	require_once 'FusionAuthClient.php';
	\$config = require 'config.php';
	\$fa = new FusionAuthClient(\$config['fusionauth']);
	try {
		// Try to get tenants to test connection
		\$response = file_get_contents(\$config['fusionauth']['base_url'] . '/api/tenant', false, stream_context_create([
			'http' => [
				'method' => 'GET',
				'header' => 'Authorization: ' . \$config['fusionauth']['api_key'],
				'timeout' => 10
			]
		]));
		if (\$response !== false) {
			echo \"✅ FusionAuth connection successful\n\";
			echo \"   Base URL: {\$config['fusionauth']['base_url']}\n\";
			echo \"   Tenant ID: {\$config['fusionauth']['tenant_id']}\n\";
		} else {
			echo \"❌ FusionAuth connection failed\n\";
			exit(1);
		}
	} catch (Exception \$e) {
		echo \"❌ FusionAuth connection failed: \" . \$e->getMessage() . \"\n\";
		exit(1);
	}
	"

# Test complete setup
test-setup:
	@echo "Testing complete setup..."
	cd user-import && php test_setup.php

# Show database statistics
stats:
	@echo "Database statistics:"
	@cd user-import && php -r "
	require_once 'config.php';
	require_once 'DatabaseClient.php';
	\$config = require 'config.php';
	\$db = new DatabaseClient(\$config['database']);
	\$stats = \$db->getStats();
	echo \"📊 Database Statistics:\n\";
	echo \"   Total users: {\$stats['total_users']}\n\";
	echo \"   Verified users: {\$stats['verified_users']}\n\";
	echo \"   Unverified users: {\$stats['unverified_users']}\n\";
	echo \"   Users by level:\n\";
	foreach (\$stats['users_by_level'] as \$level => \$count) {
		echo \"     Level {\$level}: {\$count} users\n\";
	}
	"

# Clean log files
clean-logs:
	@echo "Cleaning log files..."
	@cd user-import && rm -f *.log
	@echo "✅ Log files cleaned"

# Setup environment file
setup:
	@echo "Setting up environment file..."
	@cd user-import && if [ ! -f .env ]; then \
		echo "Creating .env file from template..."; \
		cp env.example .env 2>/dev/null || echo "No env.example found, creating basic .env..."; \
		echo "Please edit .env file with your configuration"; \
	else \
		echo ".env file already exists"; \
	fi

# Show last log entries
logs:
	@echo "Last 20 log entries:"
	@cd user-import && if [ -f user_import.log ]; then \
		tail -20 user_import.log; \
	else \
		echo "No log file found"; \
	fi

# Show log file size
log-size:
	@echo "Log file size:"
	@cd user-import && if [ -f user_import.log ]; then \
		ls -lh user_import.log; \
	else \
		echo "No log file found"; \
	fi 