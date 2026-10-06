#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

# Принимаем только версии вида v0.12.1.
release="${1:-}"
if [[ ! "$release" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo "Использование: deploy.sh v0.12.1" >&2
    exit 1
fi

cd /var/www/pizdec

# Не допускаем два одновременных деплоя
exec 9>/var/lock/pizdec-deploy.lock
flock -n 9 || {
    echo "Другой деплой уже выполняется." >&2
    exit 1
}

export RELEASE_TAG="$release"

compose=(
    docker compose
    --env-file .env.infrastructure.local
    -f docker-compose.yml
    -f docker-compose.prod.yaml
    -f docker-compose.release.yaml
)

"${compose[@]}" config --quiet

# Скачиваем только образы приложения.
# MinIO и остальные внутренние сервисы не обновляем
"${compose[@]}" pull php frontend nginx

backup_dir=/var/backups/pizdec
install -d -m 700 "$backup_dir"
backup="$backup_dir/$(date -u +%Y%m%dT%H%M%SZ)-${release}.dump"

maintenance_started=0

on_error() {
    echo "Деплой завершился ошибкой." >&2

    if (( maintenance_started )); then
        echo "Приложение может оставаться остановленным." >&2
        echo "Не запускайте старую версию без проверки состояния миграций." >&2
    fi

    echo "Путь резервной копии: $backup" >&2
    echo "При ошибке pg_dump файл .partial нельзя считать готовой копией." >&2
}
trap on_error ERR

# Останавливаем приложение и фоновые процессы перед копией и миграциями
maintenance_started=1
"${compose[@]}" stop frontend php-scheduler php-reports-worker php

# Согласованная резервная копия PostgreSQL
"${compose[@]}" exec -T db sh -ec \
    'exec pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc' \
    > "${backup}.partial"

# Проверяем, что архив читается
"${compose[@]}" exec -T db pg_restore --list \
    < "${backup}.partial" > /dev/null

mv "${backup}.partial" "$backup"
echo "Резервная копия создана: $backup"

# Применяем миграции из новой версии образа
"${compose[@]}" run --rm --no-deps --pull never php \
    php bin/console doctrine:migrations:migrate \
    --env=prod --no-interaction

# Обновляем адреса контейнеров после их перезапуска.
"${compose[@]}" exec -T nginx nginx -s reload
"${compose[@]}" exec -T frontend nginx -s reload

# Даём приложению время запуститься
ready=0
for attempt in {1..30}; do
    if curl --fail --silent --max-time 5 \
        http://127.0.0.1:8081/api/products > /dev/null; then
        ready=1
        break
    fi
    sleep 2
done

if (( ! ready )); then
    echo "API не прошло проверку после деплоя." >&2
    false
fi

maintenance_started=0
printf '%s\n' "$release" > "$backup_dir/current-release"
echo "Версия $release запущена, API отвечает."