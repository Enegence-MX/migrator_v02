# Migrator v02

Data migration and synchronization system built with Laravel 8, designed to automate the transfer and processing of catalog data, measurements, and invoices between different data sources.

## Badges

![Tests](https://github.com/Enegence-MX/migrator_v02/workflows/Tests/badge.svg)
![Code Standards](https://github.com/Enegence-MX/migrator_v02/workflows/Code%20Standards/badge.svg)
![Coverage](https://img.shields.io/endpoint?url=https://gist.githubusercontent.com/AlonsoIbarraMCK/decfbe595880f359e35398e70d4db73a/raw/migrator-v02-coverage.json)
![PHP Version](https://img.shields.io/badge/php-8.1%20%7C%208.2-blue)
![License](https://img.shields.io/badge/license-MIT-green)

## Features

- **Data Synchronization**: Automated synchronization of catalog data, measurements, and general data
- **Multi-Source Integration**: Connects to MySQL and Oracle databases seamlessly
- **Invoice Processing**: Handles Acciona and Simsa invoice generation and processing
- **Kualion Reports**: Generate calculation reports and bulk load to Oracle using SQL\*Loader (sqlldr)
- **High-Performance Data Transfer**: Uses SQL\*Loader for efficient bulk data loading into Oracle databases
- **Scheduled Tasks**: Automated execution via Laravel's task scheduler (Cron)
- **Docker Support**: Fully containerized development and production environment
- **Unit Testing**: Comprehensive test coverage with PHPUnit (30+ unit tests)
- **Code Standards**: PSR-2 compliant code with automated validation and CI/CD integration

## Technology Stack

### Core Technologies

| Component | Version | Description |
|-----------|---------|-------------|
| **Laravel Framework** | 8.83.29 | PHP web application framework |
| **PHP** | 8.1.34 | Server-side scripting language (supports 7.3 - 8.2) |
| **Apache** | 2.4 | Web server (via php:8.1-apache) |
| **MySQL** | 8.0 | Primary relational database |
| **Oracle Database** | 11g+ | Enterprise database (via OCI8) |
| **SQL\*Loader** | Latest | Oracle bulk data loading utility |

### SQL\*Loader Integration
Oracle's **SQL\*Loader** (sqlldr) is used for high-performance bulk data loading:
- Transfers calculation reports from MySQL to Oracle databases
- Processes CSV files with control files (.ctl) for precise data mapping
- Capable of loading thousands of records per second
- Used primarily in `KualionCalculationRepository` for report generation

### PHP Extensions
- `pdo_mysql` - PDO driver for MySQL
- `mysqlnd` - MySQL Native Driver
- `oci8` - Oracle Database connectivity
- `xdebug` 3.2.0 - Debugging and profiling
- `gd`, `zip`, `xml`, `bcmath`, `intl` - Core utilities

### Production Dependencies

| Package | Version | Purpose |
|---------|---------|---------|
| guzzlehttp/guzzle | 7.15.5 | HTTP client for API requests |
| laravel/sanctum | 2.15.1 | API authentication system |
| laravel/tinker | 2.11.1 | Interactive REPL console |
| fruitcake/laravel-cors | 2.2.0 | CORS middleware |

### Development Dependencies

| Package | Version | Purpose |
|---------|---------|---------|
| phpunit/phpunit | 9.6.36 | Unit testing framework |
| squizlabs/php_codesniffer | 4.0.4 | PSR-2 code standards validation |
| mockery/mockery | 1.6.15 | Test mocking framework |
| fakerphp/faker | 1.24.1 | Fake data generation |
| laravel/sail | 1.0.1 | Docker development environment |
| nunomaduro/collision | 5.10+ | Beautiful error reporting |

## System Requirements

- Docker Engine 20.10+
- Docker Compose 2.0+
- 2GB RAM minimum
- 5GB disk space
- Oracle Instant Client 19c+ (for SQL\*Loader functionality)

## Docker Installation

### macOS

```bash
# Install Docker Desktop
brew install --cask docker

# Start Docker Desktop
open -a Docker
```

### Linux (Ubuntu/Debian)

```bash
# Update package index
sudo apt-get update

# Install dependencies
sudo apt-get install ca-certificates curl gnupg lsb-release


# Install Docker Engine
sudo apt-get update
sudo apt-get install docker-ce docker-ce-cli containerd.io docker-compose-plugin

# Verify installation
docker --version
docker compose version
```

### Windows

Download and install [Docker Desktop for Windows](https://docs.docker.com/desktop/install/windows-install/)

## Installation and Setup

### 1. Clone the Repository

```bash
git clone git@github.com:Enegence-MX/migrator_v02.git
cd migrator_v02
```

### 2. Environment Configuration

```bash
# Copy environment file
cp .env.example .env

# Edit .env with your database credentials
nano .env
```

Required environment variables:
```env
APP_NAME="Migrator v02"
APP_ENV=local
APP_DEBUG=true

# MySQL Configuration
DB_CONNECTION=mysql
DB_HOST=migrator_v02_database
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=root
DB_PASSWORD=your_password

# Oracle/Kualion Configuration
KUALION_DB_USERNAME=your_username
KUALION_DB_PASSWORD=your_password
KUALION_DB_HOST=your_host
KUALION_DB_PORT=1521
KUALION_DB_SERVICE_NAME=your_service
```

### 3. Build Docker Images

```bash
# Build and start containers
docker compose up -d --build

# Check container status
docker compose ps
```

Expected output:
```
NAME                     STATUS              PORTS
migrator_v02             Up                  0.0.0.0:8006->80/tcp
migrator_v02_database    Up                  127.0.0.1:33061->3306/tcp
```

### 4. Install Dependencies

```bash
# Enter the container
docker compose exec migrator bash

# Install PHP dependencies
composer install

# Generate application key
php artisan key:generate

# Exit container
exit
```

### 5. Database Setup

```bash
# Run migrations
docker compose exec migrator php artisan migrate

# Seed database (if applicable)
docker compose exec migrator php artisan db:seed
```

## Running the Application

### Start Containers

```bash
# Start all services
docker compose up -d

# View logs
docker compose logs -f migrator
```

### Stop Containers

```bash
# Stop all services
docker compose down

# Stop and remove volumes
docker compose down -v
```

### Access the Application

- **Web Interface**: http://localhost:8006
- **MySQL Database**: localhost:33061

## Running Unit Tests

### Run All Tests

```bash
# From host machine
docker compose exec -T migrator php vendor/bin/phpunit

# Or from inside container
docker compose exec migrator bash
./vendor/bin/phpunit
```

### Run Specific Test Suite

```bash
# Run only Unit tests
docker compose exec -T migrator php vendor/bin/phpunit --testsuite Unit

# Run only Feature tests
docker compose exec -T migrator php vendor/bin/phpunit --testsuite Feature
```

### Run Tests with Coverage

```bash
# Generate HTML coverage report
docker compose exec -T migrator php vendor/bin/phpunit --coverage-html coverage/html

# Generate text coverage report
docker compose exec -T migrator php vendor/bin/phpunit --coverage-text

# Generate Clover XML (for CI/CD)
docker compose exec -T migrator php vendor/bin/phpunit --coverage-clover coverage/clover.xml
```

Coverage reports are saved in the `coverage/` directory.

### Run Specific Test File

```bash
docker compose exec -T migrator php vendor/bin/phpunit tests/Unit/Helpers/CalculationHelperTest.php
```

## Code Standards

This project follows PSR-2 coding standards.

### Check Code Standards

```bash
# From host machine
bash code_sniffer.sh

# Check specific file
bash code_sniffer.sh SyncCatalogData.php

# From inside container
docker compose exec migrator php vendor/bin/phpcs --standard=PSR2 app
```

### Auto-fix Code Standards

```bash
# Fix all files
docker compose exec -T migrator php vendor/bin/phpcbf --standard=PSR2 app

# Fix specific file
docker compose exec -T migrator php vendor/bin/phpcbf --standard=PSR2 app/Console/Commands/SyncCatalogData.php
```

## Project Structure

```
migrator_v02/
├── app/
│   ├── Console/
│   │   └── Commands/          # Sync commands
│   │       ├── SyncCatalogData.php
│   │       ├── SyncCloudCatalogData.php
│   │       ├── SyncGeneralData.php
│   │       ├── SyncMeasurements.php
│   │       └── SyncLiquidacionesEcd.php
│   └── Http/
│       └── Repositories/      # Data repositories
│           ├── MeasurementsRepo.php
│           ├── MediMEMRepo.php
│           ├── AccionaInvoiceRepository.php
│           ├── SimsaInvoiceRepository.php
│           └── KualionCalculationRepository.php
├── tests/
│   ├── Unit/                  # Unit tests
│   │   ├── Helpers/
│   │   ├── Middleware/
│   │   ├── Models/
│   │   ├── Repositories/
│   │   ├── Services/
│   │   └── Traits/
│   └── Feature/               # Integration tests
├── .github/
│   └── workflows/             # CI/CD workflows
│       ├── code-standards.yml
│       └── tests.yml
├── docker-compose.yml         # Docker services configuration
├── Dockerfile                 # Application container
├── phpunit.xml               # PHPUnit configuration
└── code_sniffer.sh           # Code standards script
```

## Verify Installation

After setup, verify all components are correctly installed:

```bash
# Check PHP version
docker compose exec migrator php -v

# Check installed PHP extensions
docker compose exec migrator php -m

# Check Laravel version
docker compose exec migrator php artisan --version

# Check Composer dependencies
docker compose exec migrator composer show

# Check database connectivity
docker compose exec migrator php artisan migrate:status

# Verify Oracle OCI8 extension (for SQL*Loader)
docker compose exec migrator php -m | grep oci8
```

Expected outputs:
- PHP: 8.1.34
- Laravel Framework: 8.83.29
- OCI8 extension should be listed (if Oracle connectivity is configured)

## Available Commands

### Data Synchronization

```bash
# Sync catalog data
docker compose exec migrator php artisan sync:catalog-data

# Sync cloud catalog data
docker compose exec migrator php artisan sync:cloud-catalog-data

# Sync general data
docker compose exec migrator php artisan sync:general-data

# Sync measurements
docker compose exec migrator php artisan sync:measurements

# Sync liquidaciones ECD
docker compose exec migrator php artisan sync:liquidaciones-ecd
```

### Scheduled Tasks

View scheduled tasks:
```bash
docker compose exec migrator php artisan schedule:list
```

## Continuous Integration

This project uses GitHub Actions for automated testing and code quality checks.

### Workflows

- **Tests**: Runs PHPUnit tests on every push/PR
- **Code Standards**: Validates PSR-2 compliance on every push/PR


## Troubleshooting

### Container won't start

```bash
# Check logs
docker compose logs migrator

# Rebuild containers
docker compose down
docker compose up -d --build
```

### Permission issues

```bash
# Fix storage permissions
docker compose exec migrator chmod -R 777 storage bootstrap/cache
```

### Database connection issues

```bash
# Check if database is running
docker compose ps

# Restart database
docker compose restart migrator_v02_database

# Verify credentials in .env match docker-compose.yml
```

### Clear cache

```bash
docker compose exec migrator php artisan cache:clear
docker compose exec migrator php artisan config:clear
docker compose exec migrator php artisan route:clear
docker compose exec migrator php artisan view:clear
```

## License

This project is licensed under the MIT License.

