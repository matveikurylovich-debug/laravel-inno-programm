package rabbitmq

import (
	"context"
	"io"
	"testing"
	"time"

	amqp "github.com/rabbitmq/amqp091-go"
	"github.com/shopspring/decimal"
	"github.com/stretchr/testify/require"

	"analytics-service/internal/domain"
)

type ackRec struct {
	acked, requeued, rejected bool
}

func (a *ackRec) Ack(uint64, bool) error {
	a.acked = true
	return nil
}

func (a *ackRec) Nack(_ uint64, _, requeue bool) error {
	a.requeued = requeue
	return nil
}

func (a *ackRec) Reject(_ uint64, requeue bool) error {
	a.rejected = !requeue
	return nil
}

type memSink struct {
	err    error
	events []domain.Event
}

func (m *memSink) InsertMany(_ context.Context, events []domain.Event) error {
	if m.err != nil {
		return m.err
	}
	m.events = append(m.events, events...)
	return nil
}

func (m *memSink) Ping(context.Context) error { return nil }

func TestHandleRejectsInvalidPayload(t *testing.T) {
	rec := &ackRec{}
	handle(context.Background(), &memSink{}, amqp.Delivery{Acknowledger: rec, Body: []byte(`{`)})
	require.True(t, rec.rejected)
	require.False(t, rec.acked)
}

func TestHandleRequeuesOnTransientInsertError(t *testing.T) {
	previous := backoffInitial
	backoffInitial = 0
	t.Cleanup(func() { backoffInitial = previous })

	rec := &ackRec{}
	body := []byte(`{"event":"user.registered","event_id":"e1","user_id":1,"occurred_at":"2026-01-01T00:00:00Z"}`)
	handle(context.Background(), &memSink{err: io.ErrUnexpectedEOF}, amqp.Delivery{Acknowledger: rec, Body: body})
	require.True(t, rec.requeued)
	require.False(t, rec.acked)
}

func TestHandleAcksAValidEvent(t *testing.T) {
	rec := &ackRec{}
	body := []byte(`{"event":"search.performed","event_id":"e1","query":"phone","occurred_at":"2026-01-01T00:00:00Z"}`)
	sink := &memSink{}
	handle(context.Background(), sink, amqp.Delivery{Acknowledger: rec, Body: body})
	require.True(t, rec.acked)
	require.Len(t, sink.events, 1)
}

func TestHandleAcksOrderRefunded(t *testing.T) {
	rec := &ackRec{}
	body := []byte(`{"event":"order.refunded","event_id":"refund-1","order_id":"11111111-1111-1111-1111-111111111111","user_id":4,"store_id":1,"refunded_amount":"15.50","occurred_at":"2026-01-01T00:00:00Z"}`)
	sink := &memSink{}
	handle(context.Background(), sink, amqp.Delivery{Acknowledger: rec, Body: body})
	require.True(t, rec.acked)
	refunded, ok := sink.events[0].(domain.OrderRefunded)
	require.True(t, ok)
	require.True(t, refunded.RefundedAmount.Equal(decimal.RequireFromString("15.50")))
}

func TestRoutingKeysIncludeRefund(t *testing.T) {
	require.Contains(t, routingKeys, domain.OrderRefundedName)
}

func TestBatchReadyBySizeAndByTime(t *testing.T) {
	previousLimit, previousWait := batchLimit, batchWait
	batchLimit, batchWait = 500, time.Second
	t.Cleanup(func() {
		batchLimit = previousLimit
		batchWait = previousWait
	})

	require.False(t, batchReady(1, 10*time.Millisecond))
	require.True(t, batchReady(1, time.Second))
	require.True(t, batchReady(500, 0))
}
