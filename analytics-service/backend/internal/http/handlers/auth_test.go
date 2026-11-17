package handlers

import (
    "net/http"
    "net/http/httptest"
    "testing"
    "time"
    "github.com/golang-jwt/jwt/v5"
)

func TestReportsRequireAccessTokenAndRole(t *testing.T) {
    secret := "a-test-only-secret-at-least-32-characters"
    for _, tc := range []struct { name, role, kind, key string; expired bool; status int }{
        {"admin", "admin", "access", secret, false, 204},
        {"analyst", "analyst", "access", secret, false, 204},
        {"customer", "customer", "access", secret, false, 403},
        {"refresh", "admin", "refresh", secret, false, 401},
        {"forged", "admin", "access", "different-test-key-at-least-32-chars", false, 401},
        {"expired", "admin", "access", secret, true, 401},
    } {
        t.Run(tc.name, func(t *testing.T) {
            expiry := time.Now().Add(time.Hour)
            if tc.expired { expiry = time.Now().Add(-time.Hour) }
            token, err := jwt.NewWithClaims(jwt.SigningMethodHS256, jwt.MapClaims{
                "sub": 1, "role": tc.role, "type": tc.kind, "iss": "https://localhost", "exp": expiry.Unix(),
            }).SignedString([]byte(tc.key))
            if err != nil { t.Fatal(err) }
            handler := RequireReportsAccess(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) { w.WriteHeader(204) }), secret, "https://localhost")
            req := httptest.NewRequest("GET", "/analytics/orders", nil)
            req.Header.Set("Authorization", "Bearer " + token)
            response := httptest.NewRecorder()
            handler.ServeHTTP(response, req)
            if response.Code != tc.status { t.Fatalf("got %d, want %d", response.Code, tc.status) }
        })
    }
}

func TestMissingCredentialsAndPublicHealth(t *testing.T) {
    handler := RequireReportsAccess(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) { w.WriteHeader(204) }), "", "")
    for path, want := range map[string]int{"/analytics/users":401, "/analytics/search":401, "/health":204} {
        response := httptest.NewRecorder()
        handler.ServeHTTP(response, httptest.NewRequest("GET", path, nil))
        if response.Code != want { t.Fatalf("%s: got %d want %d", path, response.Code, want) }
    }
}
