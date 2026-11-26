package config

import (
	"testing"

	"github.com/stretchr/testify/require"
)

func TestValidateRejectsMissingJWKSAndCredentials(t *testing.T) {
	base := Config{
		JWTIssuer:   "http://auth-app",
		JWTAudience: "ecommerce",
		JWKSURL:     "http://auth-nginx/api/v1/internal/jwks",
		HTTPPort:    "8080",
		ClickHouse: ClickHouse{
			Addr:     "analytics-clickhouse:9000",
			Database: "analytics",
			User:     "default",
			Password: "strong-password",
		},
		Rabbit: Rabbit{
			URL:      "amqp://app:strong-password@analytics-rabbitmq:5672/",
			Exchange: "analytics.events",
		},
	}
	require.NoError(t, base.Validate())

	noJWKS := base
	noJWKS.JWKSURL = ""
	require.Error(t, noJWKS.Validate())

	noClickHousePassword := base
	noClickHousePassword.ClickHouse.Password = ""
	require.Error(t, noClickHousePassword.Validate())

	noRabbitURL := base
	noRabbitURL.Rabbit.URL = ""
	require.Error(t, noRabbitURL.Validate())
}

func TestLoadPrefersAnalyticsHTTPPortAndHasNoHardcodedCredentials(t *testing.T) {
	t.Setenv("ANALYTICS_HTTP_PORT", "")
	t.Setenv("HTTP_PORT", "")
	t.Setenv("CLICKHOUSE_PASSWORD", "")
	t.Setenv("RABBITMQ_URL", "")

	defaults := Load()
	require.Equal(t, "8080", defaults.HTTPPort)
	require.Empty(t, defaults.ClickHouse.Password)
	require.Empty(t, defaults.Rabbit.URL)

	t.Setenv("HTTP_PORT", "8091")
	require.Equal(t, "8091", Load().HTTPPort)

	t.Setenv("ANALYTICS_HTTP_PORT", "9095")
	require.Equal(t, "9095", Load().HTTPPort)
}
