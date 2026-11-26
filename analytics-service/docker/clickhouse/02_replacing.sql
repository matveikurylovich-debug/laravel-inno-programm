CREATE TABLE IF NOT EXISTS analytics.schema_migrations (
    id String,
    applied_at DateTime DEFAULT now()
) ENGINE = MergeTree
ORDER BY id;

INSERT INTO analytics.schema_migrations (id)
SELECT '02_replacing'
WHERE (SELECT count() FROM analytics.schema_migrations WHERE id = '02_replacing') = 0;
