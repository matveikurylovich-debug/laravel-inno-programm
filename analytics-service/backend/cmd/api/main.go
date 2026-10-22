package main

import (
	"context"
	"errors"
	"log"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"analytics-service/internal/broker/rabbitmq"
	"analytics-service/internal/config"
	"analytics-service/internal/http/handlers"
	"analytics-service/internal/storage/clickhouse"
)

func main() {
	cfg := config.Load()

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
		Handler:           handlers.New(handlers.Reports{Users: store.Users, Orders: store.Orders, Search: store.Search}),
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
