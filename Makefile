ifneq (,$(wildcard .env))
include .env
export
endif

ifneq (,$(wildcard .env.local))
include .env.local
export
endif

up:
	docker compose up --build

down:
	docker compose down

restart:
	docker compose down
	docker compose up --build

logs:
	docker compose logs -f

ps:
	docker compose ps

prod-up:
	docker compose -f docker-compose.yml -f docker-compose.prod.yaml up --build -d

prod-down:
	docker compose -f docker-compose.yml -f docker-compose.prod.yaml down
