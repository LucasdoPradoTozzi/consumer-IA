# Laravel AI Worker

A Laravel 12 (PHP 8.4) AI-powered worker application with Docker, featuring RabbitMQ message consumption, Ollama LLM integration, and automated job processing pipeline.

## Tech Stack

- **Laravel 12** - PHP Framework (Worker Mode)
- **Docker** - Containerization
- **PostgreSQL** - Database
- **Redis** - Cache & Session Storage
- **RabbitMQ** - Message Queue
- **Ollama** - Local LLM (Qwen 2.5 7B)
- **Nginx** - Web Server
- **PHP-FPM 8.4** - PHP Process Manager

## Features

- 🤖 AI-powered job processing with Ollama LLM
- 📨 RabbitMQ message consumption with manual ACK
- 📄 PDF generation for cover letters and resumes
- ✉️ Email service integration
- 🔍 Multi-stage job classification and scoring
- 📊 Comprehensive logging at each pipeline step
- 🐳 Fully Dockerized with service orchestration

## Prerequisites

- Docker
- Docker Compose

## Installation

1. Clone the repository
```bash
git clone git@github.com:LucasdoPradoTozzi/docker-laravel.git
cd docker-laravel
```

2. Copy the environment file
```bash
cp .env.example .env
```

3. Build and start the Docker containers
```bash
docker-compose up --build -d
```

4. Install PHP dependencies
```bash
docker exec laravelapp-php composer install
```

5. Generate application key
```bash
docker exec laravelapp-php php artisan key:generate
```

6. Run database migrations
```bash
docker exec laravelapp-php php artisan migrate
```

7. Pull Ollama model (required for AI processing)
```bash
# Using profile (recommended)
./pull-ollama-model.sh low     # For development (4GB RAM)
./pull-ollama-model.sh medium  # Balanced (8GB RAM)
./pull-ollama-model.sh high    # Production (20GB RAM)

# Or manually with specific model
./pull-ollama-model.sh qwen2.5:7b-q4
```

8. Check all services
```bash
docker exec laravelapp-php php artisan worker:check
```

## Access the Application

- **Application**: http://localhost:8000
- **RabbitMQ Management**: http://localhost:15672 (admin/secret)
- **Ollama API**: http://localhost:11434

## Worker Commands

```bash
# Check service availability
docker exec laravelapp-php php artisan worker:check

# Start RabbitMQ consumer
docker exec laravelapp-php php artisan worker:consume

# Test Ollama directly
docker exec -it laravelapp-ollama ollama run qwen2.5:7b-q4

# Pull different profile models
./pull-ollama-model.sh low     # qwen2.5:7b-q4
./pull-ollama-model.sh medium  # qwen2.5:14b
./pull-ollama-model.sh high    # qwen2.5vl:32b
```

## Available Commands

```bash
# Start all containers
docker-compose up -d

# Stop containers
docker-compose down

# View logs (all services)
docker-compose logs -f

# View logs (specific service)
docker-compose logs -f app
docker-compose logs -f ollama
docker-compose logs -f rabbitmq

# Access PHP container
docker exec -it laravelapp-php sh

# Run artisan commands
docker exec laravelapp-php php artisan <command>
```

## Configuration

The application uses internal Docker networking. Services communicate via container names:

- `RABBITMQ_HOST=rabbitmq` (not localhost)
- `OLLAMA_URL=http://ollama:11434` (not localhost)
- `DB_HOST=postgres`
- `REDIS_HOST=redis`

Update `.env` with your specific configurations:

```env
# RabbitMQ
RABBITMQ_HOST=rabbitmq
RABBITMQ_USER=admin
RABBITMQ_PASSWORD=secret
RABBITMQ_QUEUE=jobs

# Ollama (AI Model Configuration)
OLLAMA_URL=http://ollama:11434
OLLAMA_PROFILE=low  # Options: low, medium, high
OLLAMA_TIMEOUT=600

# Ollama Profiles:
# - low:    qwen2.5:7b-q4     (~4GB RAM)  - Development
# - medium: qwen2.5:14b       (~8GB RAM)  - Balanced
# - high:   qwen2.5vl:32b     (~20GB RAM) - Production with vision

# Email (for job recommendations)
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password

# Processing
PROCESSING_SCORE_THRESHOLD=70
```

## GPU Support (Optional)

To enable GPU acceleration for Ollama, uncomment the deploy section in `docker-compose.yml`:

```yaml
ollama:
  deploy:
    resources:
      reservations:
        devices:
          - driver: nvidia
            count: 1
            capabilities: [gpu]
```

Requires NVIDIA Docker runtime installed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
