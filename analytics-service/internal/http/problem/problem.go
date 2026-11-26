package problem

import (
	"encoding/json"
	"log/slog"
	"net/http"
)

func Write(w http.ResponseWriter, status int, detail string) {
	w.Header().Set("Content-Type", "application/problem+json")
	w.WriteHeader(status)
	if err := json.NewEncoder(w).Encode(map[string]any{
		"type":   "about:blank",
		"title":  title(status),
		"status": status,
		"detail": detail,
	}); err != nil {
		slog.Error("problem response encode failed", "err", err)
	}
}

func title(status int) string {
	switch status {
	case http.StatusBadRequest:
		return "Bad Request"
	case http.StatusUnauthorized:
		return "Unauthorized"
	case http.StatusForbidden:
		return "Forbidden"
	case http.StatusNotFound:
		return "Not Found"
	case http.StatusConflict:
		return "Conflict"
	case http.StatusUnprocessableEntity:
		return "Unprocessable Entity"
	case http.StatusTooManyRequests:
		return "Too Many Requests"
	case http.StatusServiceUnavailable:
		return "Service Unavailable"
	default:
		return "Internal Server Error"
	}
}
