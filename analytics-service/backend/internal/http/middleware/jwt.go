package middleware

import (
	"crypto/rsa"
	"encoding/base64"
	"encoding/json"
	"errors"
	"math/big"
	"net/http"
	"strings"
	"time"

	"github.com/golang-jwt/jwt/v5"
)

type AccessClaims struct {
	jwt.RegisteredClaims
	Roles []string `json:"roles"`
	Type  string   `json:"type"`
}

type jwksDocument struct {
	Keys []struct {
		Kty string `json:"kty"`
		Kid string `json:"kid"`
		Alg string `json:"alg"`
		N   string `json:"n"`
		E   string `json:"e"`
	} `json:"keys"`
}

func KeysFromJWKS(document []byte) (map[string]*rsa.PublicKey, error) {
	var parsed jwksDocument
	if err := json.Unmarshal(document, &parsed); err != nil {
		return nil, err
	}
	keys := map[string]*rsa.PublicKey{}
	for _, key := range parsed.Keys {
		if key.Kty != "RSA" || key.Alg != "RS256" || key.Kid == "" {
			continue
		}
		public, err := rsaPublic(key.N, key.E)
		if err != nil {
			return nil, err
		}
		keys[key.Kid] = public
	}
	if len(keys) == 0 {
		return nil, errors.New("jwks has no RS256 keys")
	}

	return keys, nil
}

func RequireReportsAccess(next http.Handler, keys map[string]*rsa.PublicKey, issuer, audience string) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path == "/health" {
			next.ServeHTTP(w, r)
			return
		}

		header := r.Header.Get("Authorization")
		token := strings.TrimPrefix(header, "Bearer ")
		if header == "" || token == header || strings.TrimSpace(token) == "" {
			writeJSON(w, http.StatusUnauthorized, map[string]string{"error": "Unauthorized"})
			return
		}

		claims := &AccessClaims{}
		parsed, err := jwt.ParseWithClaims(token, claims, func(token *jwt.Token) (any, error) {
			kid, _ := token.Header["kid"].(string)
			key := keys[kid]
			if key == nil {
				return nil, errors.New("unknown kid")
			}

			return key, nil
		}, jwt.WithValidMethods([]string{"RS256"}), jwt.WithIssuer(issuer), jwt.WithAudience(audience), jwt.WithExpirationRequired(), jwt.WithLeeway(30*time.Second))
		if err != nil || !parsed.Valid || claims.Type != "access" || claims.Subject == "" || claims.ID == "" || claims.NotBefore == nil {
			writeJSON(w, http.StatusUnauthorized, map[string]string{"error": "Unauthorized"})
			return
		}

		if !hasRole(claims.Roles, "admin") && !hasRole(claims.Roles, "analyst") {
			writeJSON(w, http.StatusForbidden, map[string]string{"error": "Forbidden"})
			return
		}

		next.ServeHTTP(w, r)
	})
}

func hasRole(roles []string, want string) bool {
	for _, role := range roles {
		if strings.EqualFold(role, want) {
			return true
		}
	}

	return false
}

func rsaPublic(modulus, exponent string) (*rsa.PublicKey, error) {
	nBytes, err := base64.RawURLEncoding.DecodeString(modulus)
	if err != nil {
		return nil, err
	}
	eBytes, err := base64.RawURLEncoding.DecodeString(exponent)
	if err != nil {
		return nil, err
	}
	e := 0
	for _, b := range eBytes {
		e = e<<8 + int(b)
	}
	if e == 0 {
		return nil, errors.New("invalid exponent")
	}

	return &rsa.PublicKey{N: new(big.Int).SetBytes(nBytes), E: e}, nil
}

func writeJSON(w http.ResponseWriter, status int, payload any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(payload)
}
