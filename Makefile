.PHONY: up down logs test lint analyse prod prod-down load

up:
	docker compose up -d --build

down:
	docker compose down

logs:
	docker compose logs -f app

test:
	docker compose exec -T \
		-e APP_ENV=testing \
		-e DB_DATABASE=flashmall_testing \
		-e QUEUE_CONNECTION=sync \
		-e SESSION_DRIVER=array \
		app php artisan test

lint:
	docker compose exec -T app vendor/bin/pint --test

analyse:
	docker compose exec -T app vendor/bin/phpstan analyse --memory-limit=1G --no-progress

prod:
	docker compose --env-file .env.prod -f docker-compose.prod.yml up -d --build

prod-down:
	docker compose --env-file .env.prod -f docker-compose.prod.yml down

load:
	./load/run.sh
