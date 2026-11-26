package analytics

import (
	"encoding/json"
	"testing"
	"time"

	"github.com/shopspring/decimal"
	"github.com/stretchr/testify/require"
)

func TestBuildFunnelOmitsCompleted(t *testing.T) {
	funnel := BuildFunnel(10, 4, 3)

	require.Equal(t, uint64(10), funnel.Created)
	require.Equal(t, uint64(4), funnel.Paid)
	require.Equal(t, uint64(3), funnel.Cancelled)

	payload, err := json.Marshal(funnel)
	require.NoError(t, err)
	require.NotContains(t, string(payload), "completed")
}

func TestBuildConversionIsCappedAtOne(t *testing.T) {
	conversion := BuildConversion(8, 2, 4)

	require.InDelta(t, 0.25, conversion.CreatedToPaid, 0.0001)
	require.InDelta(t, 0.5, conversion.CreatedToCancelled, 0.0001)
	require.Equal(t, Conversion{}, BuildConversion(0, 5, 1))

	capped := BuildConversion(4, 10, 9)
	require.Equal(t, 1.0, capped.CreatedToPaid)
	require.Equal(t, 1.0, capped.CreatedToCancelled)
}

func TestNetRevenueSubtractsRefunds(t *testing.T) {
	paid := decimal.RequireFromString("100.00")
	cancelledRefund := decimal.RequireFromString("25.50")
	refundEvent := decimal.RequireFromString("10.00")

	net := NetRevenue(paid, cancelledRefund.Add(refundEvent))

	require.True(t, net.Equal(decimal.RequireFromString("64.50")))
	require.True(t, NetRevenue(decimal.RequireFromString("10"), decimal.RequireFromString("25")).Equal(decimal.RequireFromString("-15.00")))
}

func TestAverageCheck(t *testing.T) {
	require.True(t, AverageCheck(decimal.RequireFromString("301.00"), 2).Equal(decimal.RequireFromString("150.50")))
	require.True(t, AverageCheck(decimal.RequireFromString("100"), 0).Equal(decimal.Zero))
}

func TestOrdersReportJSONDropsUnusedFields(t *testing.T) {
	payload, err := json.Marshal(OrdersReport{
		Funnel:       BuildFunnel(2, 1, 1),
		Conversion:   BuildConversion(2, 1, 1),
		Revenue:      NewMoney(decimal.RequireFromString("10.5")),
		AverageCheck: NewMoney(decimal.RequireFromString("10.5")),
	})
	require.NoError(t, err)

	body := string(payload)
	require.Contains(t, body, `"revenue":10.50`)
	require.Contains(t, body, `"average_check":10.50`)
	require.NotContains(t, body, `"revenue":"10.50"`)
	require.NotContains(t, body, "completed")
	require.NotContains(t, body, "series")
	require.NotContains(t, body, "value")
}

func TestUserPointJSONUsesCount(t *testing.T) {
	payload, err := json.Marshal(Point{Period: "2026-01-01T00:00:00Z", Count: 3})
	require.NoError(t, err)
	require.JSONEq(t, `{"period":"2026-01-01T00:00:00Z","count":3}`, string(payload))
}

func TestNormalizeRangeDefaultsToLast30Days(t *testing.T) {
	to := time.Date(2026, 10, 22, 12, 0, 0, 0, time.UTC)
	rng := NormalizeRange(time.Time{}, to, "year")

	require.Equal(t, "day", rng.Granularity)
	require.Equal(t, to, rng.To)
	require.Equal(t, to.AddDate(0, 0, -30), rng.From)
}
