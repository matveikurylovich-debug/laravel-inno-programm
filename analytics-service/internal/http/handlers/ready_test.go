package handlers

import (
	"context"
	"errors"
	"net/http"
	"net/http/httptest"
	"testing"

	"github.com/stretchr/testify/require"
)

func TestReadyWithoutClickHouseIsUnavailable(t *testing.T) {
	handler := New(Reports{}, ReadyCheck{
		Ping:      func(context.Context) error { return errors.New("clickhouse unavailable") },
		Connected: func() bool { return true },
	})
	response := httptest.NewRecorder()
	handler.ServeHTTP(response, httptest.NewRequest(http.MethodGet, "/ready", nil))
	require.Equal(t, http.StatusServiceUnavailable, response.Code)
}

func TestInvalidFromDateReturns400(t *testing.T) {
	handler := New(Reports{}, ReadyCheck{})
	response := httptest.NewRecorder()
	request := httptest.NewRequest(http.MethodGet, "/analytics/users?from=yesterday", nil)
	handler.ServeHTTP(response, request)
	require.Equal(t, http.StatusBadRequest, response.Code)
}
