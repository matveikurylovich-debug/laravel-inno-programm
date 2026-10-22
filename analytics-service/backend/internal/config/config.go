package config

import "os"

type Config struct {
	HTTPPort           string
	ClickHouseAddr     string
	ClickHouseDB       string
	ClickHouseUser     string
	ClickHousePassword string
	RabbitURL          string
	RabbitExchange     string
}

func Load() Config {
	return Config{
		HTTPPort:           env("HTTP_PORT", "8080"),
		ClickHouseAddr:     env("CLICKHOUSE_ADDR", "analytics-clickhouse:9000"),
		ClickHouseDB:       env("CLICKHOUSE_DB", "analytics"),
		ClickHouseUser:     env("CLICKHOUSE_USER", "default"),
		ClickHousePassword: env("CLICKHOUSE_PASSWORD", "secret"),
		RabbitURL:          env("RABBITMQ_URL", "amqp://guest:guest@analytics-rabbitmq:5672/"),
		RabbitExchange:     env("RABBITMQ_EXCHANGE", "analytics.events"),
	}
}

func env(key, fallback string) string {
	if value := os.Getenv(key); value != "" {
		return value
	}

	return fallback
}
