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
`http://localhost:8080`

## Swagger / OpenAPI

Интерактивная документация API доступна по адресу:
`http://localhost:8080/api/doc`

OpenAPI-схема в JSON:
`http://localhost:8080/api/doc.json`

Запросы к корзине и заказам требуют JWT. Получить токен можно через
`POST /api/login_check`, передав email и пароль пользователя. Затем вставь
значение поля `token` в Swagger через кнопку **Authorize**. Указывай только
токен, без кавычек и слова `Bearer`.

## Локализация API

API поддерживает русский (`ru`) и английский (`en`) языки. Язык ответа
выбирается по заголовку `Accept-Language`.

Если заголовок отсутствует или содержит неподдерживаемый язык, API использует
русский язык. Например, `Accept-Language: de` приведёт к русскому ответу.

Если в каталоге выбранного языка отсутствует ключ перевода, Symfony использует
английский каталог как fallback.

Итого:

- fallback при выборе языка запроса — `ru`;
- fallback при отсутствии ключа перевода — `en`.

## Production-конфигурация

Используйте два явно указанных Compose-файла, чтобы dev override не подключался.
PHP собирается без dev-зависимостей; исходники находятся в образах.
Внутренние сервисы не публикуют порты. Frontend слушает только 127.0.0.1
на FRONTEND_PORT; доступ к серверу — через SSH-туннель (см. ниже).

Подготовка:

- Создайте `.env.infrastructure.local` с инфраструктурными переменными из
  корневого `.env`: порты, POSTGRES_*, RABBITMQ_*, MINIO_* и PHP_TARGET=prod.
  Задайте production credentials, не используйте локальные пароли.
- Создайте `.env.production.local`: APP_SECRET, DEFAULT_URI, DATABASE_URL,
  REDIS_URL, MESSENGER_TRANSPORT_DSN, MINIO_ENDPOINT, MINIO_ACCESS_KEY,
  MINIO_SECRET_KEY, JWT_SECRET_KEY, JWT_PUBLIC_KEY, JWT_PASSPHRASE.
  Внутренние адреса используют db, redis, rabbitmq, minio и контейнерные порты.
  Credentials приложения должны соответствовать инфраструктурным переменным.
- Разместите JWT-ключи в `.secrets/jwt/`. Пути внутри контейнера начинаются с
  `/var/www/app/config/jwt/`. Приватный ключ должен читаться PHP-процессом;
  каталог монтируется только для чтения.
- Реальные env-файлы и приватные ключи не добавляйте в Git.

Проверка конфигурации и сборка:

```bash
docker compose --env-file .env.infrastructure.local -f docker-compose.yml -f docker-compose.prod.yaml config --quiet
docker compose --env-file .env.infrastructure.local -f docker-compose.yml -f docker-compose.prod.yaml build
```

VITE_API_BASE_URL передаётся при сборке frontend, по умолчанию `/api`.
После изменения адреса пересоберите образ.
Данные PostgreSQL, RabbitMQ и MinIO сохраняются в именованных volumes.
Не используйте `down --volumes` для production: это удаляет данные.

## Деплой и откат

Пуш тега вида `v0.12.3` запускает GitHub Actions: проверки → публикация образов в GHCR → деплой по SSH.
Для SSH используются Repository secrets `DEPLOY_SSH_KEY` и `DEPLOY_KNOWN_HOSTS`.
Для ручного деплоя на сервере выполните:

```bash
bash /var/www/pizdec/deploy/deploy.sh v0.12.1
```

Скрипт сохраняет копию БД в `/var/backups/pizdec`, применяет миграции,
перезапускает приложение и проверяет API.

Откат на предыдущую версию:

```bash
bash /var/www/pizdec/deploy/deploy.sh v0.12.0
```

Откат допустим при совместимой схеме БД: скрипт не отменяет миграции
и не восстанавливает БД автоматически.

Для повторного деплоя снова выполните команду с `v0.12.1`; новый пуш не нужен.
Проверен переход `v0.12.1 → v0.12.0 → v0.12.1`: API отвечает, заголовок
страницы соответствует версии.

Просмотр через SSH-туннель — выполнить на своём ПК:

```bash
ssh -L 18081:127.0.0.1:8081 root@155.212.138.177
```

Оставьте соединение открытым и перейдите на `http://localhost:18081`.
Автоматический деплой `v0.12.3` проверен. API: `http://localhost:18081/api/products`.
По согласованию с ментором публичный домен пропущен; публичный HTTPS не настроен.
