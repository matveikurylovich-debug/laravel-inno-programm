package config

import (
	"errors"
	"os"
)

type Config struct {
	JWKSURL            string
	JWKSJSON           string
	JWTIssuer          string
	JWTAudience        string
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
		JWKSURL:            os.Getenv("JWT_JWKS_URL"),
		JWKSJSON:           os.Getenv("JWT_JWKS_JSON"),
		JWTIssuer:          env("JWT_ISSUER", "http://auth-app"),
		JWTAudience:        env("JWT_AUDIENCE", "ecommerce"),
		HTTPPort:           env("HTTP_PORT", "8080"),
		ClickHouseAddr:     env("CLICKHOUSE_ADDR", "analytics-clickhouse:9000"),
		ClickHouseDB:       env("CLICKHOUSE_DB", "analytics"),
		ClickHouseUser:     env("CLICKHOUSE_USER", "default"),
		ClickHousePassword: env("CLICKHOUSE_PASSWORD", "secret"),
		RabbitURL:          env("RABBITMQ_URL", "amqp://guest:guest@analytics-rabbitmq:5672/"),
		RabbitExchange:     env("RABBITMQ_EXCHANGE", "analytics.events"),
	}
}

func (c Config) Validate() error {
	if c.JWTIssuer == "" || c.JWTAudience == "" || (c.JWKSURL == "" && c.JWKSJSON == "") {
		return errors.New("JWT_ISSUER, JWT_AUDIENCE and JWT_JWKS_URL are required")
	}

	return nil
}

func env(key, fallback string) string {
	if value := os.Getenv(key); value != "" {
		return value
	}

	return fallback
}
