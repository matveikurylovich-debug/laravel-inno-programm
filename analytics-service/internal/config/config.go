package config

import (
	"errors"
	"os"
)

type ClickHouse struct {
	Addr     string
	Database string
	User     string
	Password string
}

type Rabbit struct {
	URL      string
	Exchange string
}

type Config struct {
	JWKSURL     string
	JWKSJSON    string
	JWTIssuer   string
	JWTAudience string
	HTTPPort    string
	ClickHouse  ClickHouse
	Rabbit      Rabbit
}

func Load() Config {
	return Config{
		JWKSURL:     os.Getenv("JWT_JWKS_URL"),
		JWKSJSON:    os.Getenv("JWT_JWKS_JSON"),
		JWTIssuer:   env("JWT_ISSUER", "http://auth-app"),
		JWTAudience: env("JWT_AUDIENCE", "ecommerce"),
		HTTPPort:    httpPort(),
		ClickHouse: ClickHouse{
			Addr:     env("CLICKHOUSE_ADDR", "analytics-clickhouse:9000"),
			Database: env("CLICKHOUSE_DB", "analytics"),
			User:     env("CLICKHOUSE_USER", "default"),
			Password: os.Getenv("CLICKHOUSE_PASSWORD"),
		},
		Rabbit: Rabbit{
			URL:      os.Getenv("RABBITMQ_URL"),
			Exchange: env("RABBITMQ_EXCHANGE", "analytics.events"),
		},
	}
}

func (c Config) Validate() error {
	if c.JWTIssuer == "" || c.JWTAudience == "" || (c.JWKSURL == "" && c.JWKSJSON == "") {
		return errors.New("JWT_ISSUER, JWT_AUDIENCE and JWT_JWKS_URL or JWT_JWKS_JSON are required")
	}
	if c.ClickHouse.Addr == "" || c.ClickHouse.Database == "" || c.ClickHouse.User == "" || c.ClickHouse.Password == "" {
		return errors.New("CLICKHOUSE_ADDR, CLICKHOUSE_DB, CLICKHOUSE_USER and CLICKHOUSE_PASSWORD are required")
	}
	if c.Rabbit.URL == "" || c.Rabbit.Exchange == "" {
		return errors.New("RABBITMQ_URL and RABBITMQ_EXCHANGE are required")
	}

	return nil
}

func httpPort() string {
	if value := os.Getenv("ANALYTICS_HTTP_PORT"); value != "" {
		return value
	}

	return env("HTTP_PORT", "8080")
}

func env(key, fallback string) string {
	if value := os.Getenv(key); value != "" {
		return value
	}

	return fallback
}
