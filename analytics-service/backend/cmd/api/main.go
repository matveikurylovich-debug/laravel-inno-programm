package main

import (
	"context"
	"crypto/rsa"
	"errors"
	"io"
	"log"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"analytics-service/internal/broker/rabbitmq"
	"analytics-service/internal/config"
	"analytics-service/internal/http/handlers"
	"analytics-service/internal/http/middleware"
	"analytics-service/internal/storage/clickhouse"
)

func main() {
	cfg := config.Load()
	if err := cfg.Validate(); err != nil {
		log.Fatalf("config: %v", err)
	}

	store, err := clickhouse.Open(cfg)
	if err != nil {
		log.Fatalf("clickhouse: %v", err)
	}

	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	defer stop()

	go func() {
		if err := rabbitmq.Run(ctx, cfg, store); err != nil && !errors.Is(err, context.Canceled) {
			log.Printf("consumer stopped: %v", err)
		}
	}()

	server := &http.Server{
		Addr:              ":" + cfg.HTTPPort,
		Handler:           middleware.RequireReportsAccess(handlers.New(handlers.Reports{Users: store.Users, Orders: store.Orders, Search: store.Search}), mustKeys(cfg), cfg.JWTIssuer, cfg.JWTAudience),
		ReadHeaderTimeout: 5 * time.Second,
	}

	go func() {
		<-ctx.Done()
		shutdownCtx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
		defer cancel()
		_ = server.Shutdown(shutdownCtx)
	}()

	log.Printf("http: listening :%s", cfg.HTTPPort)
	if err := server.ListenAndServe(); err != nil && !errors.Is(err, http.ErrServerClosed) {
		log.Fatal(err)
	}
}

func mustKeys(cfg config.Config) map[string]*rsa.PublicKey {
	document := []byte(cfg.JWKSJSON)
	if cfg.JWKSJSON == "" {
		response, err := http.Get(cfg.JWKSURL)
		if err != nil {
			log.Fatalf("jwks: %v", err)
		}
		defer response.Body.Close()
		document, err = io.ReadAll(response.Body)
		if err != nil || response.StatusCode >= 300 {
			log.Fatalf("jwks: status %d", response.StatusCode)
		}
	}
	keys, err := middleware.KeysFromJWKS(document)
	if err != nil {
		log.Fatalf("jwks: %v", err)
	}

	return keys
}
