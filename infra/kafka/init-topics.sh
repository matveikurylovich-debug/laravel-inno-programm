#!/bin/bash
set -eu
BOOTSTRAP="${KAFKA_BOOTSTRAP:-kafka:9092}"
create() {
  /opt/kafka/bin/kafka-topics.sh --bootstrap-server "$BOOTSTRAP" --create --if-not-exists \
    --topic "$1" --partitions "$2" --replication-factor 1
}
create auth.events 3
create auth.events.dlq.notification-service 1
create order.events 3
create order.events.dlq.catalog-service 1
create order.events.dlq.notification-service 1
create stock.commands 3
create stock.commands.dlq.catalog-service 1
create inventory.events 3
create inventory.events.dlq.order-service 1
create catalog.events 3
create catalog.events.dlq.order-service 1
create catalog.events.dlq.search-service 1
create catalog.events.dlq.recommendation-service 1
/opt/kafka/bin/kafka-topics.sh --bootstrap-server "$BOOTSTRAP" --list
