package integration

import (
	"context"
	"testing"
	"time"

	"analytics-service/internal/analytics"
	"analytics-service/internal/config"
	"analytics-service/internal/domain"
	"analytics-service/internal/storage/clickhouse"

	amqp "github.com/rabbitmq/amqp091-go"
	"github.com/stretchr/testify/require"
)

func TestIngestThreeSources(t *testing.T) {
	cfg := config.Load()
	store, err := clickhouse.Open(cfg)
	if err != nil {
		t.Skipf("clickhouse unavailable: %v", err)
	}

	conn, err := amqp.Dial(cfg.RabbitURL)
	if err != nil {
		t.Skipf("rabbitmq unavailable: %v", err)
	}
	defer conn.Close()

	channel, err := conn.Channel()
	require.NoError(t, err)
	defer channel.Close()
	require.NoError(t, channel.ExchangeDeclare(cfg.RabbitExchange, "topic", true, false, false, false, nil))

	suffix := time.Now().UTC().Format("150405.000")
	occurred := time.Now().UTC().Format(time.RFC3339)
	messages := []struct {
		key  string
		body string
	}{
		{domain.UserRegistered, `{"event":"user.registered","user_id":900001,"occurred_at":"` + occurred + `"}`},
		{domain.OrderCreated, `{"event":"order.created","order_id":"11111111-1111-1111-1111-111111111111","user_id":900001,"store_id":3,"total_amount":10.5,"occurred_at":"` + occurred + `"}`},
		{domain.OrderPaid, `{"event":"order.paid","order_id":"11111111-1111-1111-1111-111111111111","user_id":900001,"store_id":3,"total_amount":10.5,"occurred_at":"` + occurred + `"}`},
		{domain.OrderCancelled, `{"event":"order.cancelled","order_id":"22222222-2222-2222-2222-222222222222","user_id":900001,"store_id":3,"total_amount":4,"occurred_at":"` + occurred + `"}`},
		{domain.SearchPerformed, `{"event":"search.performed","query":"integration-` + suffix + `","results_count":0,"took_ms":4,"filters":{"in_stock":true},"occurred_at":"` + occurred + `"}`},
	}

	ctx := context.Background()
	for _, message := range messages {
		event, err := domain.Parse([]byte(message.body))
		require.NoError(t, err)
		require.NoError(t, store.Insert(ctx, event))
		require.NoError(t, channel.Publish(
			cfg.RabbitExchange,
			message.key,
			false,
			false,
			amqp.Publishing{ContentType: "application/json", DeliveryMode: amqp.Persistent, Body: []byte(message.body)},
		))
	}

	rng := analytics.NormalizeRange(time.Now().UTC().Add(-time.Hour), time.Now().UTC().Add(time.Hour), "day")
	orders, err := store.Orders(ctx, rng)
	require.NoError(t, err)
	require.GreaterOrEqual(t, orders.Funnel.Created, uint64(1))
	require.GreaterOrEqual(t, orders.Funnel.Paid, uint64(1))
	require.Equal(t, orders.Funnel.Paid, orders.Funnel.Completed)
	require.GreaterOrEqual(t, orders.Funnel.Cancelled, uint64(1))
	require.Greater(t, orders.Revenue, 0.0)
	require.Greater(t, orders.AverageCheck, 0.0)

	users, err := store.Users(ctx, rng)
	require.NoError(t, err)
	require.GreaterOrEqual(t, users.Total, uint64(1))

	searches, err := store.Search(ctx, rng)
	require.NoError(t, err)
	require.Contains(t, queries(searches.WithoutResults), "integration-"+suffix)
}

func queries(rows []analytics.SearchRow) []string {
	names := make([]string, 0, len(rows))
	for _, row := range rows {
		names = append(names, row.Query)
	}

	return names
}
