package config

import "testing"

func TestValidateRejectsMissingJWKS(t *testing.T) {
	cfg := Config{JWTIssuer: "http://auth-app", JWTAudience: "ecommerce"}
	if err := cfg.Validate(); err == nil {
		t.Fatal("expected a startup error")
	}
}
