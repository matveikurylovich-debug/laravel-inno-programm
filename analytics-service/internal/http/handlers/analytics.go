package handlers

import (
	"context"
	"encoding/json"
	"log/slog"
	"net/http"
	"time"

	"analytics-service/internal/analytics"
	"analytics-service/internal/http/problem"
)

const reportUnavailable = "Report unavailable"

type Reports struct {
	Users  func(context.Context, analytics.Range) (analytics.UsersReport, error)
	Orders func(context.Context, analytics.Range) (analytics.OrdersReport, error)
	Search func(context.Context, analytics.Range) (analytics.SearchReport, error)
}

type ReadyCheck struct {
	Ping      func(context.Context) error
	Connected func() bool
}

func New(reports Reports, ready ReadyCheck) http.Handler {
	mux := http.NewServeMux()
	mux.HandleFunc("GET /health", func(w http.ResponseWriter, _ *http.Request) {
		writeJSON(w, http.StatusOK, map[string]string{"status": "ok"})
	})
	mux.HandleFunc("GET /ready", func(w http.ResponseWriter, r *http.Request) {
		if ready.Ping == nil || ready.Connected == nil || !ready.Connected() {
			writeJSON(w, http.StatusServiceUnavailable, map[string]string{"status": "not_ready"})
			return
		}
		pingCtx, cancel := context.WithTimeout(r.Context(), 2*time.Second)
		defer cancel()
		if err := ready.Ping(pingCtx); err != nil {
			slog.ErrorContext(r.Context(), "readiness ping failed", "err", err)
			writeJSON(w, http.StatusServiceUnavailable, map[string]string{"status": "not_ready"})
			return
		}
		writeJSON(w, http.StatusOK, map[string]string{"status": "ready"})
	})
	mux.HandleFunc("GET /analytics/users", func(w http.ResponseWriter, r *http.Request) {
		rng, err := rangeFrom(r)
		if err != nil {
			problem.Write(w, http.StatusBadRequest, "Invalid date range")
			return
		}
		report, err := reports.Users(r.Context(), rng)
		writeReport(w, r, report, err)
	})
	mux.HandleFunc("GET /analytics/orders", func(w http.ResponseWriter, r *http.Request) {
		rng, err := rangeFrom(r)
		if err != nil {
			problem.Write(w, http.StatusBadRequest, "Invalid date range")
			return
		}
		report, err := reports.Orders(r.Context(), rng)
		writeReport(w, r, report, err)
	})
	mux.HandleFunc("GET /analytics/search", func(w http.ResponseWriter, r *http.Request) {
		rng, err := rangeFrom(r)
		if err != nil {
			problem.Write(w, http.StatusBadRequest, "Invalid date range")
			return
		}
		report, err := reports.Search(r.Context(), rng)
		writeReport(w, r, report, err)
	})

	return mux
}

func rangeFrom(r *http.Request) (analytics.Range, error) {
	fromRaw := r.URL.Query().Get("from")
	toRaw := r.URL.Query().Get("to")
	var from, to time.Time
	var err error
	if fromRaw != "" {
		from, err = time.Parse(time.DateOnly, fromRaw)
		if err != nil {
			return analytics.Range{}, err
		}
	}
	if toRaw != "" {
		to, err = time.Parse(time.DateOnly, toRaw)
		if err != nil {
			return analytics.Range{}, err
		}
		to = to.Add(24 * time.Hour)
	}

	return analytics.NormalizeRange(from, to, r.URL.Query().Get("granularity")), nil
}

func writeReport(w http.ResponseWriter, r *http.Request, payload any, err error) {
	if err != nil {
		slog.ErrorContext(r.Context(), "report failed", "path", r.URL.Path, "err", err)
		problem.Write(w, http.StatusInternalServerError, reportUnavailable)
		return
	}

	writeJSON(w, http.StatusOK, payload)
}

func writeJSON(w http.ResponseWriter, status int, payload any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	if err := json.NewEncoder(w).Encode(payload); err != nil {
		slog.Error("response encode failed", "err", err)
	}
}
