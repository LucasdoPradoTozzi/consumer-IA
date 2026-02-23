# Makefile for Consumer-IA

.PHONY: up down restart logs update shell

up:
	docker-compose up -d

down:
	docker-compose down

restart: down up

# Update images and restart (User Request: "bring always updated")
update:
	docker-compose pull
	docker-compose up -d --build

logs:
	docker-compose logs -f

logs-ollama:
	docker logs -f consumerIA-ollama

shell:
	docker exec -it consumerIA-php bash

status:
	docker ps
