package main

import (
	"context"
	"crypto/rsa"
	"errors"
	"fmt"
	"io"
	"log/slog"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"golang.org/x/sync/errgroup"

	"analytics-service/internal/broker/rabbitmq"
	"analytics-service/internal/config"
	"analytics-service/internal/http/handlers"
	"analytics-service/internal/http/middleware"
	"analytics-service/internal/storage/clickhouse"
)

func main() {
	cfg := config.Load()
	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	err := run(ctx, cfg)
	stop()
	if err != nil {
		slog.Error("fatal", "err", err)
		os.Exit(1)
	}
}

func run(ctx context.Context, cfg config.Config) error {
	if err := cfg.Validate(); err != nil {
		return fmt.Errorf("config: %w", err)
	}

	store, err := clickhouse.Open(ctx, cfg.ClickHouse)
	if err != nil {
		return fmt.Errorf("clickhouse: %w", err)
	}
	defer func() { _ = store.Close() }()

	keys, err := loadKeys(ctx, cfg)
	if err != nil {
		return err
	}

	server := &http.Server{
		Addr: ":" + cfg.HTTPPort,
		Handler: middleware.RequireReportsAccess(handlers.New(handlers.Reports{
			Users:  store.Users,
			Orders: store.Orders,
			Search: store.Search,
		}, handlers.ReadyCheck{
			Ping:      store.Ping,
			Connected: rabbitmq.Connected,
		}), keys, cfg.JWTIssuer, cfg.JWTAudience),
		ReadHeaderTimeout: 5 * time.Second,
		WriteTimeout:      30 * time.Second,
		IdleTimeout:       120 * time.Second,
	}

	group, groupCtx := errgroup.WithContext(ctx)
	group.Go(func() error { return rabbitmq.Run(groupCtx, cfg.Rabbit, store) })
	group.Go(func() error {
		if err := server.ListenAndServe(); err != nil && !errors.Is(err, http.ErrServerClosed) {
			return err
		}
		return nil
	})
	group.Go(func() error {
		<-groupCtx.Done()
		shutdownCtx, cancel := context.WithTimeout(context.WithoutCancel(groupCtx), 10*time.Second)
		defer cancel()
		return server.Shutdown(shutdownCtx)
	})

	if err := group.Wait(); err != nil && !errors.Is(err, context.Canceled) {
		return err
	}

	return nil
}

func loadKeys(ctx context.Context, cfg config.Config) (map[string]*rsa.PublicKey, error) {
	document := []byte(cfg.JWKSJSON)
	if cfg.JWKSJSON == "" {
		reqCtx, cancel := context.WithTimeout(ctx, 10*time.Second)
		defer cancel()

		req, err := http.NewRequestWithContext(reqCtx, http.MethodGet, cfg.JWKSURL, http.NoBody)
		if err != nil {
			return nil, fmt.Errorf("jwks request: %w", err)
		}

		client := &http.Client{Timeout: 10 * time.Second}
		response, err := client.Do(req) //nolint:gosec // JWKS endpoint is configured by operator environment
		if err != nil {
			return nil, fmt.Errorf("jwks: %w", err)
		}
		defer func() { _ = response.Body.Close() }()

		document, err = io.ReadAll(response.Body)
		if err != nil {
			return nil, fmt.Errorf("jwks read: %w", err)
		}
		if response.StatusCode >= http.StatusMultipleChoices {
			return nil, fmt.Errorf("jwks unexpected status %d", response.StatusCode)
		}
	}

	keys, err := middleware.KeysFromJWKS(document)
	if err != nil {
		return nil, fmt.Errorf("jwks: %w", err)
	}

	return keys, nil
}
