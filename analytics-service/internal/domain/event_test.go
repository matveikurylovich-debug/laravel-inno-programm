package domain

import (
	"encoding/json"
	"maps"
	"os"
	"path/filepath"
	"sort"
	"testing"

	"github.com/shopspring/decimal"
	"github.com/stretchr/testify/require"
)

const orderID = "11111111-1111-1111-1111-111111111111"

type contractFixtureEnvelope struct {
	EventID       string                     `json:"event_id"`
	EventType     string                     `json:"event_type"`
	SchemaVersion int                        `json:"schema_version"`
	OccurredAt    string                     `json:"occurred_at"`
	Producer      string                     `json:"producer"`
	Data          map[string]json.RawMessage `json:"data"`
}

func flattenFixture(t *testing.T, raw []byte) (contractFixtureEnvelope, []byte) {
	t.Helper()

	var env contractFixtureEnvelope
	require.NoError(t, json.Unmarshal(raw, &env))
	require.Equal(t, 1, env.SchemaVersion)
	require.NotEmpty(t, env.EventID)
	require.NotEmpty(t, env.EventType)
	require.NotEmpty(t, env.OccurredAt)
	require.NotEmpty(t, env.Producer)
	require.NotEmpty(t, env.Data)

	flat := make(map[string]json.RawMessage, len(env.Data)+3)
	maps.Copy(flat, env.Data)
	backfillString(flat, "event", env.EventType)
	backfillString(flat, "event_id", env.EventID)
	backfillString(flat, "occurred_at", env.OccurredAt)

	encoded, err := json.Marshal(flat)
	require.NoError(t, err)

	return env, encoded
}

func TestParseGoldenContractFixtures(t *testing.T) {
	producerFixtures := []string{
		"../../../contracts/auth.events/user.registered.json",
		"../../../contracts/order.events/order.created.json",
		"../../../contracts/order.events/order.paid.json",
		"../../../contracts/order.events/order.cancelled.json",
		"../../../contracts/order.events/order.refunded.json",
		"../../../contracts/analytics.events/search.performed.json",
	}

	for _, fixturePath := range producerFixtures {
		t.Run("producer_"+filepath.Base(fixturePath), func(t *testing.T) {
			raw, err := os.ReadFile(fixturePath)
			require.NoError(t, err)

			event, err := Parse(raw)
			require.NoError(t, err)
			require.NoError(t, event.Validate())
		})
	}

	orderFiles, err := filepath.Glob("../../../contracts/order.events/*.json")
	require.NoError(t, err)
	sort.Strings(orderFiles)
	require.Len(t, orderFiles, 5)

	extraFiles := []string{
		"../../../contracts/auth.events/user.registered.json",
		"../../../contracts/analytics.events/search.performed.json",
	}
	orderFiles = append(orderFiles, extraFiles...)

	seenTypes := make(map[string]int, len(orderFiles))

	for _, path := range orderFiles {
		t.Run(filepath.Base(path), func(t *testing.T) {
			raw, readErr := os.ReadFile(path)
			require.NoError(t, readErr)

			env, flat := flattenFixture(t, raw)
			seenTypes[env.EventType]++

			for _, candidate := range []struct {
				label string
				body  []byte
			}{
				{label: "envelope", body: raw},
				{label: "flat", body: flat},
			} {
				event, parseErr := Parse(candidate.body)
				if env.EventType == "order.confirmed" {
					require.ErrorIs(t, parseErr, ErrInvalidEvent, candidate.label)
					continue
				}

				require.NoError(t, parseErr, candidate.label)
				require.NoError(t, event.Validate(), candidate.label)
				require.Equal(t, env.EventType, event.Name(), candidate.label)
				require.Equal(t, env.EventID, event.ID(), candidate.label)
				require.False(t, event.At().IsZero(), candidate.label)

				switch env.EventType {
				case OrderCreatedName:
					created, ok := event.(OrderCreated)
					require.True(t, ok)
					require.Equal(t, "22222222-2222-4222-8222-222222222222", created.OrderID)
					require.Equal(t, uint64(4), created.UserID)
					require.Equal(t, uint32(1), created.StoreID)
					require.True(t, created.TotalAmount.Equal(decimal.RequireFromString("1200.00")))
				case OrderPaidName:
					paid, ok := event.(OrderPaid)
					require.True(t, ok)
					require.Equal(t, "22222222-2222-4222-8222-222222222222", paid.OrderID)
					require.Equal(t, uint64(4), paid.UserID)
					require.Equal(t, uint32(1), paid.StoreID)
					require.True(t, paid.TotalAmount.Equal(decimal.RequireFromString("1200.00")))
				case OrderCancelledName:
					cancelled, ok := event.(OrderCancelled)
					require.True(t, ok)
					require.Equal(t, "22222222-2222-4222-8222-222222222222", cancelled.OrderID)
					require.Equal(t, uint64(4), cancelled.UserID)
					require.Equal(t, uint32(1), cancelled.StoreID)
					require.True(t, cancelled.TotalAmount.Equal(decimal.RequireFromString("1200.00")))
					require.True(t, cancelled.RefundedAmount.Equal(decimal.RequireFromString("0.00")))
				case OrderRefundedName:
					refunded, ok := event.(OrderRefunded)
					require.True(t, ok)
					require.Equal(t, "22222222-2222-4222-8222-222222222222", refunded.OrderID)
					require.Equal(t, uint64(4), refunded.UserID)
					require.Equal(t, uint32(1), refunded.StoreID)
					require.True(t, refunded.RefundedAmount.Equal(decimal.RequireFromString("1200.00")))
				case UserRegisteredName:
					registered, ok := event.(UserRegistered)
					require.True(t, ok)
					require.Equal(t, uint64(4), registered.UserID)
				case SearchPerformedName:
					performed, ok := event.(SearchPerformed)
					require.True(t, ok)
					require.Nil(t, performed.UserID)
					require.Equal(t, "смартфон", performed.Query)
					require.Equal(t, uint32(12), performed.ResultsCount)
					require.Equal(t, uint32(8), performed.TookMS)
					require.JSONEq(t, `{"category_id":1,"in_stock":true}`, performed.FiltersJSON)
				default:
					t.Fatalf("unhandled contract fixture %s (%s)", path, env.EventType)
				}
			}
		})
	}

	for _, expected := range []string{
		OrderCreatedName,
		OrderPaidName,
		OrderCancelledName,
		OrderRefundedName,
		"order.confirmed",
		UserRegisteredName,
		SearchPerformedName,
	} {
		require.Equal(t, 1, seenTypes[expected], "missing fixture for %s", expected)
	}
}

func TestParseUsesProducerFieldNames(t *testing.T) {
	paid, err := Parse([]byte(`{"event":"order.paid","event_id":"paid-1","order_id":"` + orderID +
		`","user_id":4,"store_id":3,"total_amount":21,"occurred_at":"2026-11-25T12:00:00+00:00"}`))
	require.NoError(t, err)
	require.True(t, paid.(OrderPaid).TotalAmount.Equal(decimal.RequireFromString("21.00")))

	fraction, err := Parse([]byte(`{"event":"order.paid","event_id":"paid-2","order_id":"` + orderID +
		`","user_id":4,"store_id":3,"total_amount":10.5,"occurred_at":"2026-11-25T12:00:00Z"}`))
	require.NoError(t, err)
	require.True(t, fraction.(OrderPaid).TotalAmount.Equal(decimal.RequireFromString("10.50")))

	quoted, err := Parse([]byte(`{"event":"order.created","event_id":"created-1","order_id":"` + orderID +
		`","user_id":4,"store_id":1,"total_amount":"19.99","occurred_at":"2026-11-25T12:00:00Z"}`))
	require.NoError(t, err)
	require.True(t, quoted.(OrderCreated).TotalAmount.Equal(decimal.RequireFromString("19.99")))

	cancelled, err := Parse([]byte(`{"event":"order.cancelled","event_id":"cancel-1","order_id":"` + orderID +
		`","user_id":4,"store_id":1,"total_amount":21,"refunded_amount":4.25,"occurred_at":"2026-11-25T12:00:00Z"}`))
	require.NoError(t, err)
	require.True(t, cancelled.(OrderCancelled).RefundedAmount.Equal(decimal.RequireFromString("4.25")))
	require.True(t, cancelled.(OrderCancelled).TotalAmount.Equal(decimal.RequireFromString("21.00")))

	withoutRefund, err := Parse([]byte(`{"event":"order.cancelled","event_id":"cancel-2","order_id":"` + orderID +
		`","user_id":4,"store_id":null,"total_amount":21,"occurred_at":"2026-11-25T12:00:00Z"}`))
	require.NoError(t, err)
	require.True(t, withoutRefund.(OrderCancelled).RefundedAmount.Equal(decimal.Zero))

	refunded, err := Parse([]byte(`{"event":"order.refunded","event_id":"refund-1","order_id":"` + orderID +
		`","user_id":4,"store_id":1,"refunded_amount":"15.50","occurred_at":"2026-11-25T12:00:00Z"}`))
	require.NoError(t, err)
	require.True(t, refunded.(OrderRefunded).RefundedAmount.Equal(decimal.RequireFromString("15.50")))

	user, err := Parse([]byte(`{"event":"user.registered","event_id":"user-1","user_id":7,"occurred_at":"2026-11-25T12:00:00+00:00"}`))
	require.NoError(t, err)
	require.Equal(t, uint64(7), user.(UserRegistered).UserID)

	search, err := Parse([]byte(`{"event":"search.performed","event_id":"search-1","query":"phone","filters":{"in_stock":true},"results_count":0,"took_ms":4,"occurred_at":"2026-11-25T12:00:00+00:00"}`))
	require.NoError(t, err)
	performed := search.(SearchPerformed)
	require.Nil(t, performed.UserID)
	require.Equal(t, "phone", performed.Query)
	require.JSONEq(t, `{"in_stock":true}`, performed.FiltersJSON)

	withUser, err := Parse([]byte(`{"event":"search.performed","event_id":"search-2","query":"case","user_id":9,"results_count":2,"took_ms":3,"occurred_at":"2026-11-25T12:00:00Z"}`))
	require.NoError(t, err)
	require.NotNil(t, withUser.(SearchPerformed).UserID)
	require.Equal(t, uint64(9), *withUser.(SearchPerformed).UserID)
}

func TestParseRejectsInvalidEvents(t *testing.T) {
	cases := []string{
		`{`,
		`{"schema_version":2,"data":{"event":"order.paid"}}`,
		`{"event":"order.shipped","event_id":"e","occurred_at":"2026-11-25T12:00:00Z"}`,
		`{"event":"order.paid","order_id":"` + orderID + `","user_id":4,"total_amount":1,"occurred_at":"2026-11-25T12:00:00Z"}`,
		`{"event":"order.paid","event_id":"e","order_id":"not-a-uuid","user_id":4,"total_amount":1,"occurred_at":"2026-11-25T12:00:00Z"}`,
		`{"event":"order.paid","event_id":"e","order_id":"` + orderID + `","total_amount":1,"occurred_at":"2026-11-25T12:00:00Z"}`,
		`{"event":"order.refunded","event_id":"e","order_id":"` + orderID + `","user_id":4,"occurred_at":"2026-11-25T12:00:00Z"}`,
		`{"event":"order.cancelled","event_id":"e","order_id":"` + orderID + `","user_id":4,"total_amount":10,"refunded_amount":-1,"occurred_at":"2026-11-25T12:00:00Z"}`,
		`{"event":"user.registered","event_id":"e","occurred_at":"2026-11-25T12:00:00Z"}`,
		`{"event":"search.performed","event_id":"e","query":"phone","occurred_at":"yesterday"}`,
		`{"event":"search.performed","event_id":"e","query":"phone","user_id":0,"occurred_at":"2026-11-25T12:00:00Z"}`,
	}
	for _, body := range cases {
		_, err := Parse([]byte(body))
		require.ErrorIs(t, err, ErrInvalidEvent, body)
	}
}

func TestValidateRejectsEmptyEvents(t *testing.T) {
	for _, ev := range []Event{
		UserRegistered{},
		OrderCreated{},
		OrderPaid{},
		OrderCancelled{},
		OrderRefunded{},
		SearchPerformed{},
	} {
		require.ErrorIs(t, ev.Validate(), ErrInvalidEvent, ev.Name())
	}
}
