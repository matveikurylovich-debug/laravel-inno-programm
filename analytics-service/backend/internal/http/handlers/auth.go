package handlers

import (
	"net/http"
	"strings"

	"github.com/golang-jwt/jwt/v5"
)

// RequireReportsAccess validates credentials in the service even on direct requests.
func RequireReportsAccess(next http.Handler, secret, issuer string) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path == "/health" {
			next.ServeHTTP(w, r)
			return
		}
		authorization := r.Header.Get("Authorization")
		if len(secret) < 32 || issuer == "" || !strings.HasPrefix(authorization, "Bearer ") {
			writeJSON(w, http.StatusUnauthorized, map[string]string{"error": "Unauthorized"})
			return
		}
		claims := jwt.MapClaims{}
		token, err := jwt.ParseWithClaims(strings.TrimPrefix(authorization, "Bearer "), claims,
			func(token *jwt.Token) (any, error) { return []byte(secret), nil },
			jwt.WithValidMethods([]string{"HS256"}), jwt.WithExpirationRequired(), jwt.WithIssuer(issuer),
		)
		if err != nil || !token.Valid || claims["type"] != "access" || claims["sub"] == nil {
			writeJSON(w, http.StatusUnauthorized, map[string]string{"error": "Unauthorized"})
			return
		}
		if claims["role"] != "admin" && claims["role"] != "analyst" {
			writeJSON(w, http.StatusForbidden, map[string]string{"error": "Forbidden"})
			return
		}
		next.ServeHTTP(w, r)
	})
}
