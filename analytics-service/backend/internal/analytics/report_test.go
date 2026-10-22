package analytics

import (
	"testing"
	"time"

	"github.com/stretchr/testify/require"
)

func TestBuildFunnelCountsPaidAsCompleted(t *testing.T) {
	funnel := BuildFunnel(10, 4, 3)

	require.Equal(t, uint64(10), funnel.Created)
	require.Equal(t, uint64(4), funnel.Paid)
	require.Equal(t, uint64(4), funnel.Completed)
	require.Equal(t, uint64(3), funnel.Cancelled)
}

func TestBuildConversion(t *testing.T) {
	conversion := BuildConversion(8, 2, 4)

	require.InDelta(t, 0.25, conversion.CreatedToPaid, 0.0001)
	require.InDelta(t, 0.5, conversion.CreatedToCancelled, 0.0001)
	require.Equal(t, Conversion{}, BuildConversion(0, 0, 0))
}

func TestAverageCheck(t *testing.T) {
	require.InDelta(t, 150.5, AverageCheck(301, 2), 0.0001)
	require.Zero(t, AverageCheck(100, 0))
}

func TestNormalizeRangeDefaultsToLast30Days(t *testing.T) {
	to := time.Date(2026, 10, 22, 12, 0, 0, 0, time.UTC)
	rng := NormalizeRange(time.Time{}, to, "year")

	require.Equal(t, "day", rng.Granularity)
	require.Equal(t, to, rng.To)
	require.Equal(t, to.AddDate(0, 0, -30), rng.From)
}

func TestPeriodSQL(t *testing.T) {
	require.Equal(t, "toStartOfDay(event_time)", PeriodSQL("day"))
	require.Equal(t, "toStartOfWeek(event_time)", PeriodSQL("week"))
	require.Equal(t, "toStartOfMonth(event_time)", PeriodSQL("month"))
	require.Equal(t, "toStartOfDay(event_time)", PeriodSQL("drop table"))
}
