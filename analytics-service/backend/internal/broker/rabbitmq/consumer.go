package rabbitmq

import (
	"context"
	"log"
	"time"

	"analytics-service/internal/config"
	"analytics-service/internal/domain"

	amqp "github.com/rabbitmq/amqp091-go"
)

const queueName = "analytics.events"

var routingKeys = []string{
	domain.UserRegistered,
	domain.OrderCreated,
	domain.OrderPaid,
	domain.OrderCancelled,
	domain.SearchPerformed,
}

type Inserter interface {
	Insert(ctx context.Context, event domain.Event) error
}

func Run(ctx context.Context, cfg config.Config, sink Inserter) error {
	for {
		if err := consume(ctx, cfg, sink); err != nil && ctx.Err() == nil {
			log.Printf("rabbitmq: %v", err)
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

func consume(ctx context.Context, cfg config.Config, sink Inserter) error {
	conn, err := amqp.Dial(cfg.RabbitURL)
	if err != nil {
		return err
	}
	defer conn.Close()

	channel, err := conn.Channel()
	if err != nil {
		return err
	}
	defer channel.Close()

	if err := channel.ExchangeDeclare(cfg.RabbitExchange, "topic", true, false, false, false, nil); err != nil {
		return err
	}
	queue, err := channel.QueueDeclare(queueName, true, false, false, false, nil)
	if err != nil {
		return err
	}
	for _, key := range routingKeys {
		if err := channel.QueueBind(queue.Name, key, cfg.RabbitExchange, false, nil); err != nil {
			return err
		}
	}
	if err := channel.Qos(20, 0, false); err != nil {
		return err
	}

	deliveries, err := channel.Consume(queue.Name, "", false, false, false, false, nil)
	if err != nil {
		return err
	}

	log.Printf("rabbitmq: listening %s", queue.Name)
	for {
		select {
		case <-ctx.Done():
			return ctx.Err()
		case delivery, ok := <-deliveries:
			if !ok {
				return amqp.ErrClosed
			}
			handle(ctx, sink, delivery)
		}
	}
}

func handle(ctx context.Context, sink Inserter, delivery amqp.Delivery) {
	event, err := domain.Parse(delivery.Body)
	if err != nil {
		log.Printf("rabbitmq: drop invalid message: %v", err)
		_ = delivery.Ack(false)
		return
	}

	if err := sink.Insert(ctx, event); err != nil {
		log.Printf("rabbitmq: insert %s failed: %v", event.Name, err)
		_ = delivery.Nack(false, false)
		return
	}

	_ = delivery.Ack(false)
}

func Declare(url, exchange string) error {
	conn, err := amqp.Dial(url)
	if err != nil {
		return err
	}
	defer conn.Close()

	channel, err := conn.Channel()
	if err != nil {
		return err
	}
	defer channel.Close()

	return channel.ExchangeDeclare(exchange, "topic", true, false, false, false, nil)
}
