CREATE DATABASE IF NOT EXISTS analytics;

CREATE TABLE IF NOT EXISTS analytics.events_users (
    event_id String,
    user_id UInt64,
    event_time DateTime('UTC'),
    inserted_at DateTime DEFAULT now()
) ENGINE = ReplacingMergeTree(inserted_at)
PARTITION BY toYYYYMM(event_time)
ORDER BY (user_id);

CREATE TABLE IF NOT EXISTS analytics.events_orders (
    event_id String,
    order_id UUID,
    user_id UInt64,
    store_id UInt32,
    status LowCardinality(String),
    total_amount Decimal64(2),
    refunded_amount Decimal64(2) DEFAULT 0,
    event_time DateTime('UTC'),
    inserted_at DateTime DEFAULT now()
) ENGINE = ReplacingMergeTree(inserted_at)
PARTITION BY toYYYYMM(event_time)
ORDER BY (status, order_id);

CREATE TABLE IF NOT EXISTS analytics.events_search (
    event_id String,
    query String,
    results_count UInt32,
    took_ms UInt32,
    filters_json String,
    user_id Nullable(UInt64),
    event_time DateTime('UTC'),
    inserted_at DateTime DEFAULT now()
) ENGINE = ReplacingMergeTree(inserted_at)
PARTITION BY toYYYYMM(event_time)
ORDER BY (toDate(event_time), event_id);
