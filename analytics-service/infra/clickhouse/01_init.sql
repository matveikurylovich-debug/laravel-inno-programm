CREATE DATABASE IF NOT EXISTS analytics;

-- 1. Регистрации пользователей (Auth Service: user.registered)
-- users.id — bigint, в событии user.registered user_id приходит как число
CREATE TABLE IF NOT EXISTS analytics.events_users (
    user_id UInt64,
    event_time DateTime DEFAULT now(),
    date Date DEFAULT toDate(event_time)
) ENGINE = MergeTree()
PARTITION BY toYYYYMM(date)
ORDER BY (date, event_time, user_id);

-- 2. Жизненный цикл заказов (Order Service: order.created, order.paid, order.cancelled)
-- orders.id — UUID, user_id/store_id — целые
CREATE TABLE IF NOT EXISTS analytics.events_orders (
    order_id UUID,
    user_id UInt64,
    store_id UInt32,
    status LowCardinality(String),
    total_amount Decimal64(2),
    event_time DateTime DEFAULT now(),
    date Date DEFAULT toDate(event_time)
) ENGINE = MergeTree()
PARTITION BY toYYYYMM(date)
ORDER BY (store_id, status, date, event_time, order_id);

-- 3. Поисковые запросы (Search Service: search.performed)
-- payload: query, filters, results_count, took_ms; user_id в событии пока нет
CREATE TABLE IF NOT EXISTS analytics.events_search (
    query String,
    results_count UInt32,
    took_ms UInt32,
    filters_json String,
    user_id Nullable(UInt64),
    event_time DateTime DEFAULT now(),
    date Date DEFAULT toDate(event_time)
) ENGINE = MergeTree()
PARTITION BY toYYYYMM(date)
ORDER BY (date, event_time, results_count);
