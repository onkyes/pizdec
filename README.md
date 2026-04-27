# pizdec
pet project
# pizdec

Pet project на Symfony с Docker-окружением.

## Требования

- Docker
- Docker Compose

## Структура проекта

- `app/` — Symfony-приложение
- `php/` — Dockerfile и конфиги PHP
- `nginx/` — конфиг Nginx
- `.env` — переменные инфраструктуры
- `app/.env` — переменные приложения Symfony

## Запуск проекта

1. Убедиться, что Docker запущен
2. Проверить значения в корневом `.env`
3. Проверить значения в `app/.env`
4. Собрать и поднять контейнеры:

```bash
docker compose up --build
```
После запуска приложение доступно по адресу:
http://localhost:8080
```