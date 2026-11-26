package clickhouse

import (
	"testing"
	"time"

	"github.com/shopspring/decimal"
	"github.com/stretchr/testify/require"

	"analytics-service/internal/domain"
)

func TestDuplicateEventIDIsCountedOnce(t *testing.T) {
	events := dedupeEvents([]domain.Event{
		domain.SearchPerformed{EventID: "e1", Query: "phone"},
		domain.SearchPerformed{EventID: "e1", Query: "phone"},
		domain.SearchPerformed{EventID: "e2", Query: "case"},
	})

	require.Len(t, events, 2)
	require.Equal(t, "e2", events[1].ID())
}

func TestOrderRowsKeepRefundsApartFromTotals(t *testing.T) {
	when := time.Date(2026, 11, 25, 12, 0, 0, 0, time.UTC)
	orderID := "11111111-1111-1111-1111-111111111111"

	cancelled, err := asOrderRow(domain.OrderCancelled{
		EventID:        "cancel",
		OrderID:        orderID,
		UserID:         4,
		StoreID:        1,
		TotalAmount:    decimal.RequireFromString("21.00"),
		RefundedAmount: decimal.RequireFromString("4.25"),
		OccurredAt:     when,
	})
	require.NoError(t, err)
	require.Equal(t, "cancelled", cancelled.status)
	require.True(t, cancelled.amount.Equal(decimal.RequireFromString("21.00")))
	require.True(t, cancelled.refunded.Equal(decimal.RequireFromString("4.25")))

	refunded, err := asOrderRow(domain.OrderRefunded{
		EventID:        "refund",
		OrderID:        orderID,
		UserID:         4,
		StoreID:        1,
		RefundedAmount: decimal.RequireFromString("15.50"),
		OccurredAt:     when,
	})
	require.NoError(t, err)
	require.Equal(t, "refunded", refunded.status)
	require.True(t, refunded.amount.Equal(decimal.Zero))
	require.True(t, refunded.refunded.Equal(decimal.RequireFromString("15.50")))
}
