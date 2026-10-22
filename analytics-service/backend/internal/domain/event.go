package domain

import (
	"encoding/json"
	"fmt"
	"strings"
	"time"
)

const (
	UserRegistered  = "user.registered"
	OrderCreated    = "order.created"
	OrderPaid       = "order.paid"
	OrderCancelled  = "order.cancelled"
	SearchPerformed = "search.performed"
)

type Event struct {
	Name         string
	UserID       *uint64
	OrderID      string
	StoreID      uint32
	TotalAmount  float64
	Query        string
	ResultsCount uint32
	TookMS       uint32
	FiltersJSON  string
	OccurredAt   time.Time
}

func Parse(body []byte) (Event, error) {
	var raw struct {
		Event        string          `json:"event"`
		UserID       *uint64         `json:"user_id"`
		OrderID      string          `json:"order_id"`
		StoreID      *uint64         `json:"store_id"`
		TotalAmount  float64         `json:"total_amount"`
		Query        string          `json:"query"`
		ResultsCount uint32          `json:"results_count"`
		TookMS       uint32          `json:"took_ms"`
		Filters      json.RawMessage `json:"filters"`
		OccurredAt   string          `json:"occurred_at"`
	}

	if err := json.Unmarshal(body, &raw); err != nil {
		return Event{}, fmt.Errorf("decode event: %w", err)
	}

	occurred := time.Now().UTC()
	if raw.OccurredAt != "" {
		parsed, err := time.Parse(time.RFC3339, raw.OccurredAt)
		if err != nil {
			return Event{}, fmt.Errorf("occurred_at: %w", err)
		}
		occurred = parsed.UTC()
	}

	filters := "{}"
	if len(raw.Filters) > 0 && string(raw.Filters) != "null" {
		filters = string(raw.Filters)
	}

	event := Event{
		Name:         raw.Event,
		UserID:       raw.UserID,
		OrderID:      raw.OrderID,
		TotalAmount:  raw.TotalAmount,
		Query:        raw.Query,
		ResultsCount: raw.ResultsCount,
		TookMS:       raw.TookMS,
		FiltersJSON:  filters,
		OccurredAt:   occurred,
	}
	if raw.StoreID != nil {
		event.StoreID = uint32(*raw.StoreID)
	}

	return event, nil
}

func (e Event) OrderStatus() (string, error) {
	status, ok := strings.CutPrefix(e.Name, "order.")
	if !ok || status == "" {
		return "", fmt.Errorf("not an order event: %s", e.Name)
	}

	return status, nil
}
