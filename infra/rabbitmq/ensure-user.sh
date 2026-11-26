#!/bin/sh
# RabbitMQ 4 не создаёт пользователя из RABBITMQ_DEFAULT_USER/PASS.
# default_user в конфиге срабатывает только на пустом томе, поэтому пользователя
# ещё и создаём (или обновляем пароль) после старта брокера.
set -eu

user="${RABBITMQ_DEFAULT_USER:-app}"
pass="${RABBITMQ_DEFAULT_PASS:?RABBITMQ_DEFAULT_PASS is required}"

umask 077
printf 'default_user = %s\ndefault_pass = %s\n' "$user" "$pass" > /etc/rabbitmq/conf.d/30-default-user.conf
chown rabbitmq:rabbitmq /etc/rabbitmq/conf.d/30-default-user.conf

if [ "${1:-}" != "rabbitmq-server" ]; then
  exec /usr/local/bin/docker-entrypoint.sh "$@"
fi

/usr/local/bin/docker-entrypoint.sh rabbitmq-server &
pid=$!
trap 'kill -TERM "$pid" 2>/dev/null || true; wait "$pid"' TERM INT

ready=0
i=0
while [ "$i" -lt 90 ]; do
  if su-exec rabbitmq rabbitmq-diagnostics -q check_running >/dev/null 2>&1; then
    ready=1
    break
  fi
  if ! kill -0 "$pid" 2>/dev/null; then
    wait "$pid"
    exit 1
  fi
  i=$((i + 1))
  sleep 1
done

if [ "$ready" != 1 ]; then
  echo "rabbitmq did not become ready" >&2
  exit 1
fi

user_ready=0
j=0
while [ "$j" -lt 30 ]; do
  if su-exec rabbitmq rabbitmqctl -q list_users 2>/dev/null | awk -v u="$user" '$1 == u { found = 1 } END { exit !found }'; then
    if su-exec rabbitmq rabbitmqctl change_password "$user" "$pass"; then
      user_ready=1
      break
    fi
  elif su-exec rabbitmq rabbitmqctl add_user "$user" "$pass"; then
    user_ready=1
    break
  fi
  j=$((j + 1))
  sleep 1
done

if [ "$user_ready" != 1 ]; then
  echo "rabbitmq user was not created" >&2
  exit 1
fi

su-exec rabbitmq rabbitmqctl set_user_tags "$user" administrator
su-exec rabbitmq rabbitmqctl set_permissions -p / "$user" ".*" ".*" ".*"
touch /tmp/rabbit-app-user-ready

wait "$pid"
