package rabbitmq

import (
	"context"
	"errors"
	"log/slog"
	"sync/atomic"
	"time"

	amqp "github.com/rabbitmq/amqp091-go"

	"analytics-service/internal/config"
	"analytics-service/internal/domain"
)

const (
	queueName = "analytics.events"
	dlxName   = "analytics.events.dlx"
	dlqName   = "analytics.events.dlq"
)

var (
	batchLimit     = 500
	batchWait      = time.Second
	backoffInitial = time.Second
	backoffMax     = 30 * time.Second
	connected      atomic.Bool
	routingKeys    = []string{
		domain.UserRegisteredName,
		domain.OrderCreatedName,
		domain.OrderPaidName,
		domain.OrderCancelledName,
		domain.OrderRefundedName,
		domain.SearchPerformedName,
	}
)

type Sink interface {
	InsertMany(ctx context.Context, events []domain.Event) error
	Ping(ctx context.Context) error
}

func Connected() bool {
	return connected.Load()
}

func Run(ctx context.Context, cfg config.Rabbit, sink Sink) error {
	for {
		if err := consume(ctx, cfg, sink); err != nil && ctx.Err() == nil {
			slog.Error("rabbitmq consume failed", "err", err)
		}
		if ctx.Err() != nil {
			return ctx.Err()
		}

		select {
		case <-ctx.Done():
			return ctx.Err()
		case <-time.After(2 * time.Second):
		}
	}
}

func consume(ctx context.Context, cfg config.Rabbit, sink Sink) error {
	conn, err := amqp.Dial(cfg.URL)
	if err != nil {
		return err
	}
	defer func() { _ = conn.Close() }()

	channel, err := conn.Channel()
	if err != nil {
		return err
	}
	defer func() { _ = channel.Close() }()

	if err := declareTopology(channel, cfg.Exchange); err != nil {
		return err
	}
	if err := channel.Qos(batchLimit, 0, false); err != nil {
		return err
	}

	deliveries, err := channel.Consume(queueName, "", false, false, false, false, nil)
	if err != nil {
		return err
	}
	connected.Store(true)
	defer connected.Store(false)
	slog.Info("rabbitmq listening", "queue", queueName)

	batch := make([]amqp.Delivery, 0, batchLimit)
	timer := time.NewTimer(time.Hour)
	if !timer.Stop() {
		<-timer.C
	}
	defer timer.Stop()

	for {
		if ctx.Err() != nil {
			return ctx.Err()
		}
		select {
		case <-ctx.Done():
			return ctx.Err()
		case delivery, ok := <-deliveries:
			if !ok {
				return amqp.ErrClosed
			}
			batch = append(batch, delivery)
			if len(batch) == 1 {
				timer.Reset(batchWait)
			}
			if len(batch) >= batchLimit {
				if err := flush(ctx, sink, batch); err != nil {
					return err
				}
				batch = batch[:0]
				if !timer.Stop() {
					select {
					case <-timer.C:
					default:
					}
				}
			}
		case <-timer.C:
			if len(batch) == 0 {
				continue
			}
			if err := flush(ctx, sink, batch); err != nil {
				return err
			}
			batch = batch[:0]
		}
	}
}

func declareTopology(channel *amqp.Channel, exchange string) error {
	if err := channel.ExchangeDeclare(exchange, "topic", true, false, false, false, nil); err != nil {
		return err
	}
	if err := channel.ExchangeDeclare(dlxName, "fanout", true, false, false, false, nil); err != nil {
		return err
	}
	queueArgs := amqp.Table{
		"x-queue-type":           "quorum",
		"x-dead-letter-exchange": dlxName,
		"x-delivery-limit":       int32(20),
	}
	if _, err := channel.QueueDeclare(queueName, true, false, false, false, queueArgs); err != nil {
		return err
	}
	if _, err := channel.QueueDeclare(dlqName, true, false, false, false, amqp.Table{"x-queue-type": "quorum"}); err != nil {
		return err
	}
	for _, key := range routingKeys {
		if err := channel.QueueBind(queueName, key, exchange, false, nil); err != nil {
			return err
		}
	}

	return channel.QueueBind(dlqName, "", dlxName, false, nil)
}

func handle(ctx context.Context, sink Sink, delivery amqp.Delivery) {
	if err := flush(ctx, sink, []amqp.Delivery{delivery}); err != nil {
		slog.Error("analytics message was not settled", "err", err)
	}
}

func flush(ctx context.Context, sink Sink, deliveries []amqp.Delivery) error {
	if ctx.Err() != nil {
		return ctx.Err()
	}

	valid := make([]domain.Event, 0, len(deliveries))
	tags := make([]amqp.Delivery, 0, len(deliveries))
	for _, delivery := range deliveries {
		event, err := domain.Parse(delivery.Body)
		if err != nil {
			slog.Error("invalid analytics message", "err", err, "body", string(delivery.Body))
			reject(delivery)
			continue
		}
		valid = append(valid, event)
		tags = append(tags, delivery)
	}
	if len(valid) == 0 {
		return nil
	}

	insertCtx, cancel := context.WithTimeout(context.WithoutCancel(ctx), 10*time.Second)
	err := sink.InsertMany(insertCtx, valid)
	cancel()
	if err == nil {
		return ackBatch(tags[len(tags)-1])
	}
	if errors.Is(err, domain.ErrInvalidEvent) {
		slog.Error("permanent analytics insert error", "err", err)
		for _, delivery := range tags {
			reject(delivery)
		}
		return nil
	}

	slog.Warn("transient analytics insert error", "err", err, "events", len(valid))
	if waitErr := pauseBeforeRequeue(ctx, sink); waitErr != nil {
		return waitErr
	}

	return requeueBatch(tags[len(tags)-1])
}

func pauseBeforeRequeue(ctx context.Context, sink Sink) error {
	delay := backoffInitial
	for {
		pingCtx, cancel := context.WithTimeout(ctx, 2*time.Second)
		err := sink.Ping(pingCtx)
		cancel()
		if err == nil {
			break
		}
		slog.Warn("clickhouse is not ready", "err", err, "delay", delay.String())
		select {
		case <-ctx.Done():
			return ctx.Err()
		case <-time.After(delay):
		}
		if delay < backoffMax {
			delay = min(delay*2, backoffMax)
		}
	}

	timer := time.NewTimer(backoffInitial)
	defer timer.Stop()
	select {
	case <-ctx.Done():
		return ctx.Err()
	case <-timer.C:
		return nil
	}
}

func ackBatch(delivery amqp.Delivery) error {
	if err := delivery.Ack(true); err != nil {
		slog.Error("ack failed", "err", err)
		return err
	}

	return nil
}

func requeueBatch(delivery amqp.Delivery) error {
	if err := delivery.Nack(true, true); err != nil {
		slog.Error("nack failed", "err", err)
		return err
	}

	return nil
}

func reject(delivery amqp.Delivery) {
	if err := delivery.Reject(false); err != nil {
		slog.Error("reject failed", "err", err, "body", string(delivery.Body))
	}
}

func batchReady(count int, waited time.Duration) bool {
	return count >= batchLimit || (count > 0 && waited >= batchWait)
}
