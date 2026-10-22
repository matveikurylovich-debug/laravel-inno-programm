package handlers

import (
	"context"
	"encoding/json"
	"net/http"
	"time"

	"analytics-service/internal/analytics"
)

type Reports struct {
	Users  func(context.Context, analytics.Range) (analytics.UsersReport, error)
	Orders func(context.Context, analytics.Range) (analytics.OrdersReport, error)
	Search func(context.Context, analytics.Range) (analytics.SearchReport, error)
}

func New(reports Reports) http.Handler {
	mux := http.NewServeMux()
	mux.HandleFunc("GET /health", func(w http.ResponseWriter, _ *http.Request) {
		writeJSON(w, http.StatusOK, map[string]string{"status": "ok"})
	})
	mux.HandleFunc("GET /analytics/users", func(w http.ResponseWriter, r *http.Request) {
		report, err := reports.Users(r.Context(), rangeFrom(r))
		write(w, report, err)
	})
	mux.HandleFunc("GET /analytics/orders", func(w http.ResponseWriter, r *http.Request) {
		report, err := reports.Orders(r.Context(), rangeFrom(r))
		write(w, report, err)
	})
	mux.HandleFunc("GET /analytics/search", func(w http.ResponseWriter, r *http.Request) {
		report, err := reports.Search(r.Context(), rangeFrom(r))
		write(w, report, err)
	})

	return mux
}

func rangeFrom(r *http.Request) analytics.Range {
	from, _ := time.Parse(time.DateOnly, r.URL.Query().Get("from"))
	to, _ := time.Parse(time.DateOnly, r.URL.Query().Get("to"))
	if !to.IsZero() {
		to = to.Add(24 * time.Hour)
	}

	return analytics.NormalizeRange(from, to, r.URL.Query().Get("granularity"))
}

func write(w http.ResponseWriter, payload any, err error) {
	if err != nil {
		writeJSON(w, http.StatusInternalServerError, map[string]string{"error": err.Error()})
		return
	}

	writeJSON(w, http.StatusOK, payload)
}

func writeJSON(w http.ResponseWriter, status int, payload any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(payload)
}
