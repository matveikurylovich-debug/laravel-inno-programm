package domain

import (
	"encoding/json"
	"errors"
	"fmt"
	"math"
	"strings"
	"time"

	"github.com/google/uuid"
	"github.com/shopspring/decimal"
)

const (
	UserRegisteredName  = "user.registered"
	OrderCreatedName    = "order.created"
	OrderPaidName       = "order.paid"
	OrderCancelledName  = "order.cancelled"
	OrderRefundedName   = "order.refunded"
	SearchPerformedName = "search.performed"
)

var ErrInvalidEvent = errors.New("invalid analytics event")

// Event is one analytics message. Each concrete type carries only its own fields.
type Event interface {
	Name() string
	ID() string
	At() time.Time
	Validate() error
}

type UserRegistered struct {
	EventID    string
	UserID     uint64
	OccurredAt time.Time
}

type OrderCreated struct {
	EventID     string
	OrderID     string
	UserID      uint64
	StoreID     uint32
	TotalAmount decimal.Decimal
	OccurredAt  time.Time
}

type OrderPaid struct {
	EventID     string
	OrderID     string
	UserID      uint64
	StoreID     uint32
	TotalAmount decimal.Decimal
	OccurredAt  time.Time
}

type OrderCancelled struct {
	EventID        string
	OrderID        string
	UserID         uint64
	StoreID        uint32
	TotalAmount    decimal.Decimal
	RefundedAmount decimal.Decimal
	OccurredAt     time.Time
}

type OrderRefunded struct {
	EventID        string
	OrderID        string
	UserID         uint64
	StoreID        uint32
	RefundedAmount decimal.Decimal
	OccurredAt     time.Time
}

type SearchPerformed struct {
	EventID      string
	UserID       *uint64
	Query        string
	ResultsCount uint32
	TookMS       uint32
	FiltersJSON  string
	OccurredAt   time.Time
}

func (e UserRegistered) Name() string  { return UserRegisteredName }
func (e UserRegistered) ID() string    { return e.EventID }
func (e UserRegistered) At() time.Time { return e.OccurredAt }

func (e UserRegistered) Validate() error {
	if err := validateIdentity(e.EventID, e.OccurredAt); err != nil {
		return err
	}
	if e.UserID == 0 {
		return fmt.Errorf("%w: user.registered without user_id", ErrInvalidEvent)
	}

	return nil
}

func (e OrderCreated) Name() string  { return OrderCreatedName }
func (e OrderCreated) ID() string    { return e.EventID }
func (e OrderCreated) At() time.Time { return e.OccurredAt }

func (e OrderCreated) Validate() error {
	return validateOrder(e.EventID, e.OrderID, e.UserID, e.OccurredAt, e.TotalAmount)
}

func (e OrderPaid) Name() string  { return OrderPaidName }
func (e OrderPaid) ID() string    { return e.EventID }
func (e OrderPaid) At() time.Time { return e.OccurredAt }

func (e OrderPaid) Validate() error {
	return validateOrder(e.EventID, e.OrderID, e.UserID, e.OccurredAt, e.TotalAmount)
}

func (e OrderCancelled) Name() string  { return OrderCancelledName }
func (e OrderCancelled) ID() string    { return e.EventID }
func (e OrderCancelled) At() time.Time { return e.OccurredAt }

func (e OrderCancelled) Validate() error {
	if err := validateOrder(e.EventID, e.OrderID, e.UserID, e.OccurredAt, e.TotalAmount); err != nil {
		return err
	}
	if e.RefundedAmount.IsNegative() {
		return fmt.Errorf("%w: refunded_amount must not be negative", ErrInvalidEvent)
	}

	return nil
}

func (e OrderRefunded) Name() string  { return OrderRefundedName }
func (e OrderRefunded) ID() string    { return e.EventID }
func (e OrderRefunded) At() time.Time { return e.OccurredAt }

func (e OrderRefunded) Validate() error {
	if err := validateOrder(e.EventID, e.OrderID, e.UserID, e.OccurredAt, decimal.Zero); err != nil {
		return err
	}
	if e.RefundedAmount.IsNegative() {
		return fmt.Errorf("%w: refunded_amount must not be negative", ErrInvalidEvent)
	}

	return nil
}

func (e SearchPerformed) Name() string  { return SearchPerformedName }
func (e SearchPerformed) ID() string    { return e.EventID }
func (e SearchPerformed) At() time.Time { return e.OccurredAt }

func (e SearchPerformed) Validate() error {
	if err := validateIdentity(e.EventID, e.OccurredAt); err != nil {
		return err
	}
	if e.UserID != nil && *e.UserID == 0 {
		return fmt.Errorf("%w: user_id must not be zero", ErrInvalidEvent)
	}

	return nil
}

type rawEvent struct {
	Event          string          `json:"event"`
	EventID        string          `json:"event_id"`
	UserID         *uint64         `json:"user_id"`
	OrderID        string          `json:"order_id"`
	StoreID        *uint64         `json:"store_id"`
	TotalAmount    json.RawMessage `json:"total_amount"`
	RefundedAmount json.RawMessage `json:"refunded_amount"`
	Query          string          `json:"query"`
	ResultsCount   uint32          `json:"results_count"`
	TookMS         uint32          `json:"took_ms"`
	Filters        json.RawMessage `json:"filters"`
	OccurredAt     string          `json:"occurred_at"`
}

type envelopeEvent struct {
	EventID       string                     `json:"event_id"`
	EventType     string                     `json:"event_type"`
	SchemaVersion *int                       `json:"schema_version"`
	OccurredAt    string                     `json:"occurred_at"`
	Data          map[string]json.RawMessage `json:"data"`
}

func unwrapEnvelope(body []byte) ([]byte, error) {
	var env envelopeEvent
	if err := json.Unmarshal(body, &env); err != nil {
		return nil, fmt.Errorf("%w: decode: %w", ErrInvalidEvent, err)
	}
	if env.SchemaVersion == nil || env.Data == nil {
		return body, nil
	}
	if *env.SchemaVersion != 1 {
		return nil, fmt.Errorf("%w: unsupported schema_version %d", ErrInvalidEvent, *env.SchemaVersion)
	}
	backfillString(env.Data, "event", env.EventType)
	backfillString(env.Data, "event_id", env.EventID)
	backfillString(env.Data, "occurred_at", env.OccurredAt)

	flat, err := json.Marshal(env.Data)
	if err != nil {
		return nil, fmt.Errorf("%w: decode: %w", ErrInvalidEvent, err)
	}

	return flat, nil
}

func backfillString(data map[string]json.RawMessage, key, fallback string) {
	if fallback == "" {
		return
	}
	if existing, ok := data[key]; ok && len(existing) > 0 && string(existing) != "null" && string(existing) != `""` {
		return
	}
	encoded, err := json.Marshal(fallback)
	if err == nil {
		data[key] = encoded
	}
}

func Parse(body []byte) (Event, error) {
	payload, err := unwrapEnvelope(body)
	if err != nil {
		return nil, err
	}

	var raw rawEvent
	if err := json.Unmarshal(payload, &raw); err != nil {
		return nil, fmt.Errorf("%w: decode: %w", ErrInvalidEvent, err)
	}

	eventID := strings.TrimSpace(raw.EventID)
	occurred, err := parseOccurred(raw.OccurredAt)
	if err != nil {
		return nil, err
	}
	userID := uint64(0)
	if raw.UserID != nil {
		userID = *raw.UserID
	}

	var event Event
	switch raw.Event {
	case UserRegisteredName:
		event = UserRegistered{EventID: eventID, UserID: userID, OccurredAt: occurred}
	case OrderCreatedName, OrderPaidName, OrderCancelledName, OrderRefundedName:
		order, err := orderFromRaw(raw, eventID, userID, occurred)
		if err != nil {
			return nil, err
		}
		event = order
	case SearchPerformedName:
		var searchUser *uint64
		if raw.UserID != nil {
			searchUser = raw.UserID
		}
		event = SearchPerformed{
			EventID:      eventID,
			UserID:       searchUser,
			Query:        raw.Query,
			ResultsCount: raw.ResultsCount,
			TookMS:       raw.TookMS,
			FiltersJSON:  filtersJSON(raw.Filters),
			OccurredAt:   occurred,
		}
	default:
		return nil, fmt.Errorf("%w: unknown event %s", ErrInvalidEvent, raw.Event)
	}
	if err := event.Validate(); err != nil {
		return nil, err
	}

	return event, nil
}

func orderFromRaw(raw rawEvent, eventID string, userID uint64, occurred time.Time) (Event, error) {
	orderID, err := parseOrderID(raw.OrderID)
	if err != nil {
		return nil, err
	}
	storeID, err := parseStoreID(raw.StoreID)
	if err != nil {
		return nil, err
	}

	switch raw.Event {
	case OrderCreatedName:
		amount, err := parseAmount(raw.TotalAmount, "total_amount", true)
		if err != nil {
			return nil, err
		}
		return OrderCreated{
			EventID: eventID, OrderID: orderID, UserID: userID, StoreID: storeID,
			TotalAmount: amount, OccurredAt: occurred,
		}, nil
	case OrderPaidName:
		amount, err := parseAmount(raw.TotalAmount, "total_amount", true)
		if err != nil {
			return nil, err
		}
		return OrderPaid{
			EventID: eventID, OrderID: orderID, UserID: userID, StoreID: storeID,
			TotalAmount: amount, OccurredAt: occurred,
		}, nil
	case OrderCancelledName:
		amount, err := parseAmount(raw.TotalAmount, "total_amount", true)
		if err != nil {
			return nil, err
		}
		refunded, err := parseAmount(raw.RefundedAmount, "refunded_amount", false)
		if err != nil {
			return nil, err
		}
		return OrderCancelled{
			EventID: eventID, OrderID: orderID, UserID: userID, StoreID: storeID,
			TotalAmount: amount, RefundedAmount: refunded, OccurredAt: occurred,
		}, nil
	case OrderRefundedName:
		refunded, err := parseAmount(raw.RefundedAmount, "refunded_amount", true)
		if err != nil {
			return nil, err
		}
		return OrderRefunded{
			EventID: eventID, OrderID: orderID, UserID: userID, StoreID: storeID,
			RefundedAmount: refunded, OccurredAt: occurred,
		}, nil
	default:
		return nil, fmt.Errorf("%w: unknown event %s", ErrInvalidEvent, raw.Event)
	}
}

func validateIdentity(eventID string, occurred time.Time) error {
	if strings.TrimSpace(eventID) == "" {
		return fmt.Errorf("%w: event_id is required", ErrInvalidEvent)
	}
	if occurred.IsZero() {
		return fmt.Errorf("%w: occurred_at is required", ErrInvalidEvent)
	}

	return nil
}

func validateOrder(eventID, orderID string, userID uint64, occurred time.Time, amount decimal.Decimal) error {
	if err := validateIdentity(eventID, occurred); err != nil {
		return err
	}
	if _, err := uuid.Parse(strings.TrimSpace(orderID)); err != nil {
		return fmt.Errorf("%w: order_id: %w", ErrInvalidEvent, err)
	}
	if userID == 0 {
		return fmt.Errorf("%w: order event requires order_id and user_id", ErrInvalidEvent)
	}
	if amount.IsNegative() {
		return fmt.Errorf("%w: total_amount must not be negative", ErrInvalidEvent)
	}

	return nil
}

func parseOccurred(value string) (time.Time, error) {
	if strings.TrimSpace(value) == "" {
		return time.Time{}, fmt.Errorf("%w: occurred_at is required", ErrInvalidEvent)
	}
	parsed, err := time.Parse(time.RFC3339, value)
	if err != nil {
		parsed, err = time.Parse(time.RFC3339Nano, value)
	}
	if err != nil {
		return time.Time{}, fmt.Errorf("%w: occurred_at: %w", ErrInvalidEvent, err)
	}

	return parsed.UTC(), nil
}

func parseOrderID(value string) (string, error) {
	parsed, err := uuid.Parse(strings.TrimSpace(value))
	if err != nil {
		return "", fmt.Errorf("%w: order_id: %w", ErrInvalidEvent, err)
	}

	return parsed.String(), nil
}

func parseStoreID(value *uint64) (uint32, error) {
	if value == nil {
		return 0, nil
	}
	if *value > math.MaxUint32 {
		return 0, fmt.Errorf("%w: store_id is out of range", ErrInvalidEvent)
	}

	return uint32(*value), nil
}

func parseAmount(raw json.RawMessage, field string, required bool) (decimal.Decimal, error) {
	if len(raw) == 0 || string(raw) == "null" {
		if required {
			return decimal.Decimal{}, fmt.Errorf("%w: %s is required", ErrInvalidEvent, field)
		}

		return decimal.Zero, nil
	}

	var amount decimal.Decimal
	if err := amount.UnmarshalJSON(raw); err != nil {
		return decimal.Decimal{}, fmt.Errorf("%w: %s: %w", ErrInvalidEvent, field, err)
	}
	if amount.IsNegative() {
		return decimal.Decimal{}, fmt.Errorf("%w: %s must not be negative", ErrInvalidEvent, field)
	}

	return amount.Round(2), nil
}

func filtersJSON(raw json.RawMessage) string {
	if len(raw) == 0 || string(raw) == "null" {
		return "{}"
	}

	return string(raw)
}
