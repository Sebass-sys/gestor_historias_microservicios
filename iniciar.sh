#!/usr/bin/env bash
# Inicia los 3 microservicios y el gateway (Linux / macOS). Detener con Ctrl+C.
# Requisitos: PHP 8 con pdo_mysql, MySQL encendido y database.sql ya importado.
cd "$(dirname "$0")" || exit 1

php -S 127.0.0.1:8001 -t sprints-service   sprints-service/index.php   &
php -S 127.0.0.1:8002 -t historias-service historias-service/index.php &
php -S 127.0.0.1:8003 -t informes-service  informes-service/index.php  &
php -S 127.0.0.1:8000 -t gateway           gateway/router.php          &

trap 'kill $(jobs -p) 2>/dev/null' EXIT
echo "Servicios iniciados. Abre http://127.0.0.1:8000/  (Ctrl+C para detener)"
wait
