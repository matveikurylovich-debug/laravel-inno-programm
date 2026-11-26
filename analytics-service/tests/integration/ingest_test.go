//go:build integration

package integration

import (
	"context"
	"os"
	"slices"
	"testing"
	"time"

	amqp "github.com/rabbitmq/amqp091-go"
	"github.com/stretchr/testify/require"

	"analytics-service/internal/analytics"
	"analytics-service/internal/broker/rabbitmq"
	"analytics-service/internal/config"
	"analytics-service/internal/domain"
	"analytics-service/internal/storage/clickhouse"
)

func TestIngestThreeSources(t *testing.T) {
	require.NotEmpty(t, os.Getenv("CLICKHOUSE_ADDR"), "CLICKHOUSE_ADDR must be set for integration tests")
	require.NotEmpty(t, os.Getenv("RABBITMQ_URL"), "RABBITMQ_URL must be set for integration tests")

	ctx := context.Background()
	cfg := config.Load()

	store, err := clickhouse.Open(ctx, cfg.ClickHouse)
	require.NoError(t, err)
	defer func() { _ = store.Close() }()

	consumerCtx, cancelConsumer := context.WithCancel(ctx)
	defer cancelConsumer()

	consumerDone := make(chan error, 1)
	go func() {
		consumerDone <- rabbitmq.Run(consumerCtx, cfg.Rabbit, store)
	}()

	require.Eventually(t, rabbitmq.Connected, 15*time.Second, 100*time.Millisecond, "rabbitmq consumer did not connect")

	conn, err := amqp.Dial(cfg.Rabbit.URL)
	require.NoError(t, err)
	defer func() { _ = conn.Close() }()

	channel, err := conn.Channel()
	require.NoError(t, err)
	defer func() { _ = channel.Close() }()
	require.NoError(t, channel.ExchangeDeclare(cfg.Rabbit.Exchange, "topic", true, false, false, false, nil))

	suffix := time.Now().UTC().Format("150405.000")
	occurred := time.Now().UTC().Format(time.RFC3339)
	messages := []struct {
		key  string
		body string
	}{
		{domain.UserRegisteredName, `{"event":"user.registered","event_id":"user-` + suffix + `","user_id":900001,"occurred_at":"` + occurred + `"}`},
		{domain.OrderCreatedName, `{"event":"order.created","event_id":"created-` + suffix + `","order_id":"11111111-1111-1111-1111-111111111111","user_id":900001,"store_id":3,"total_amount":10.5,"occurred_at":"` + occurred + `"}`},
		{domain.OrderPaidName, `{"event":"order.paid","event_id":"paid-` + suffix + `","order_id":"11111111-1111-1111-1111-111111111111","user_id":900001,"store_id":3,"total_amount":10.5,"occurred_at":"` + occurred + `"}`},
		{domain.OrderCancelledName, `{"event":"order.cancelled","event_id":"cancelled-` + suffix + `","order_id":"22222222-2222-2222-2222-222222222222","user_id":900001,"store_id":3,"total_amount":4,"refunded_amount":"0.00","occurred_at":"` + occurred + `"}`},
		{domain.OrderRefundedName, `{"event":"order.refunded","event_id":"refunded-` + suffix + `","order_id":"22222222-2222-2222-2222-222222222222","user_id":900001,"store_id":3,"total_amount":4,"refunded_amount":"1.50","occurred_at":"` + occurred + `"}`},
		{domain.SearchPerformedName, `{"event":"search.performed","event_id":"search-` + suffix + `","query":"integration-` + suffix + `","results_count":0,"took_ms":4,"filters":{"in_stock":true},"occurred_at":"` + occurred + `"}`},
	}

	for _, message := range messages {
		_, parseErr := domain.Parse([]byte(message.body))
		require.NoError(t, parseErr)
		require.NoError(t, channel.PublishWithContext(
			ctx,
			cfg.Rabbit.Exchange,
			message.key,
			false,
			false,
			amqp.Publishing{ContentType: "application/json", DeliveryMode: amqp.Persistent, Body: []byte(message.body)},
		))
	}

	rng := analytics.NormalizeRange(time.Now().UTC().Add(-time.Hour), time.Now().UTC().Add(time.Hour), "day")

	require.Eventually(t, func() bool {
		orders, ordersErr := store.Orders(ctx, rng)
		if ordersErr != nil {
			return false
		}
		if orders.Funnel.Created < 1 || orders.Funnel.Paid < 1 || orders.Funnel.Cancelled < 1 {
			return false
		}
		if orders.Revenue.Sign() <= 0 || orders.AverageCheck.Sign() <= 0 {
			return false
		}

		users, usersErr := store.Users(ctx, rng)
		if usersErr != nil || users.Total < 1 {
			return false
		}

		searches, searchErr := store.Search(ctx, rng)
		if searchErr != nil {
			return false
		}

		return slices.Contains(queries(searches.WithoutResults), "integration-"+suffix)
	}, 15*time.Second, 200*time.Millisecond, "events were not ingested from RabbitMQ into ClickHouse in time")

	cancelConsumer()
	require.ErrorIs(t, <-consumerDone, context.Canceled)
}

func queries(rows []analytics.SearchRow) []string {
	names := make([]string, 0, len(rows))
	for _, row := range rows {
		names = append(names, row.Query)
	}

	return names
}
