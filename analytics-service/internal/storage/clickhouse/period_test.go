package clickhouse

import "testing"

func TestPeriodSQL(t *testing.T) {
	if PeriodSQL("day") != "toStartOfDay(event_time)" {
		t.Fatalf("day: %s", PeriodSQL("day"))
	}
	if PeriodSQL("week") != "toStartOfWeek(event_time)" {
		t.Fatalf("week: %s", PeriodSQL("week"))
	}
	if PeriodSQL("month") != "toStartOfMonth(event_time)" {
		t.Fatalf("month: %s", PeriodSQL("month"))
	}
	if PeriodSQL("drop table") != "toStartOfDay(event_time)" {
		t.Fatalf("unknown granularity must stay on the day bucket")
	}
}
