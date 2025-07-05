.PHONY: build build-no-cache up down clean

# Build and start services using local docker-compose configuration
build:
	docker-compose -f docker-compose-local.yml up --build -d

# Build and start services with no-cache flag
build-no-cache:
	docker-compose -f docker-compose-local.yml build --no-cache && docker-compose -f docker-compose-local.yml up -d

# Start services (without rebuilding)
up:
	docker-compose -f docker-compose-local.yml up -d

# Stop and remove services
down:
	docker-compose -f docker-compose-local.yml down

# Stop, remove services and clean up volumes
clean:
	docker-compose -f docker-compose-local.yml down -v 