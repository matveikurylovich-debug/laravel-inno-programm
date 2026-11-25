package middleware

import (
	"crypto/rsa"
	"crypto/x509"
	"encoding/json"
	"encoding/pem"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"testing"
	"time"

	"github.com/golang-jwt/jwt/v5"
)

func TestGoldenFixturesAndRoles(t *testing.T) {
	fixtures := loadFixtures(t)
	keys, err := KeysFromJWKS(mustJSON(t, fixtures["jwks"]))
	if err != nil {
		t.Fatal(err)
	}
	handler := RequireReportsAccess(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		w.WriteHeader(http.StatusNoContent)
	}), keys, fixtures["issuer"].(string), fixtures["audience"].(string))

	admin := sign(t, fixtures, map[string]any{"roles": []string{"admin"}})
	cases := []struct {
		name   string
		token  string
		status int
	}{
		{"valid customer", fixtures["valid"].(string), http.StatusForbidden},
		{"admin", admin, http.StatusNoContent},
		{"expired", fixtures["expired"].(string), http.StatusUnauthorized},
		{"wrong type", fixtures["wrong_type"].(string), http.StatusUnauthorized},
		{"missing type", fixtures["missing_type"].(string), http.StatusUnauthorized},
		{"wrong alg", fixtures["wrong_alg"].(string), http.StatusUnauthorized},
		{"missing aud", fixtures["missing_aud"].(string), http.StatusUnauthorized},
		{"wrong aud", fixtures["wrong_aud"].(string), http.StatusUnauthorized},
		{"missing bearer", "", http.StatusUnauthorized},
	}
	for _, tc := range cases {
		t.Run(tc.name, func(t *testing.T) {
			request := httptest.NewRequest(http.MethodGet, "/analytics/orders", nil)
			if tc.token != "" {
				request.Header.Set("Authorization", "Bearer "+tc.token)
			}
			response := httptest.NewRecorder()
			handler.ServeHTTP(response, request)
			if response.Code != tc.status {
				t.Fatalf("got %d, want %d", response.Code, tc.status)
			}
		})
	}
}

func TestHealthDoesNotRequireAToken(t *testing.T) {
	handler := RequireReportsAccess(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		w.WriteHeader(http.StatusNoContent)
	}), map[string]*rsa.PublicKey{}, "http://auth-app", "ecommerce")
	response := httptest.NewRecorder()
	handler.ServeHTTP(response, httptest.NewRequest(http.MethodGet, "/health", nil))
	if response.Code != http.StatusNoContent {
		t.Fatalf("got %d", response.Code)
	}
}

func loadFixtures(t *testing.T) map[string]any {
	t.Helper()
	dir, err := os.Getwd()
	if err != nil {
		t.Fatal(err)
	}
	for {
		candidate := filepath.Join(dir, "packages", "platform-kit", "tests", "fixtures", "tokens.json")
		if _, err := os.Stat(candidate); err == nil {
			raw, err := os.ReadFile(candidate)
			if err != nil {
				t.Fatal(err)
			}
			var fixtures map[string]any
			if err := json.Unmarshal(raw, &fixtures); err != nil {
				t.Fatal(err)
			}

			return fixtures
		}
		parent := filepath.Dir(dir)
		if parent == dir {
			t.Fatal("tokens.json not found")
		}
		dir = parent
	}
}

func mustJSON(t *testing.T, value any) []byte {
	t.Helper()
	raw, err := json.Marshal(value)
	if err != nil {
		t.Fatal(err)
	}

	return raw
}

func sign(t *testing.T, fixtures map[string]any, extra map[string]any) string {
	t.Helper()
	dir, _ := os.Getwd()
	var pemPath string
	for {
		candidate := filepath.Join(dir, "packages", "platform-kit", "tests", "fixtures", "access-private.pem")
		if _, err := os.Stat(candidate); err == nil {
			pemPath = candidate
			break
		}
		parent := filepath.Dir(dir)
		if parent == dir {
			t.Fatal("private key not found")
		}
		dir = parent
	}
	raw, err := os.ReadFile(pemPath)
	if err != nil {
		t.Fatal(err)
	}
	block, _ := pem.Decode(raw)
	key, err := x509.ParsePKCS8PrivateKey(block.Bytes)
	if err != nil {
		parsed, err2 := x509.ParsePKCS1PrivateKey(block.Bytes)
		if err2 != nil {
			t.Fatal(err)
		}
		key = parsed
	}
	now := time.Now()
	claims := jwt.MapClaims{
		"iss": fixtures["issuer"], "aud": fixtures["audience"], "sub": "9", "jti": "admin-test",
		"iat": now.Unix(), "nbf": now.Unix(), "exp": now.Add(time.Hour).Unix(), "type": "access", "roles": []string{"admin"},
	}
	for keyName, value := range extra {
		claims[keyName] = value
	}
	token := jwt.NewWithClaims(jwt.SigningMethodRS256, claims)
	token.Header["kid"] = fixtures["kid"]
	signed, err := token.SignedString(key)
	if err != nil {
		t.Fatal(err)
	}

	return signed
}
