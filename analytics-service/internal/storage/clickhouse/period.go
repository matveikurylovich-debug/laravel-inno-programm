package clickhouse

func PeriodSQL(granularity string) string {
	switch granularity {
	case "week":
		return "toStartOfWeek(event_time)"
	case "month":
		return "toStartOfMonth(event_time)"
	default:
		return "toStartOfDay(event_time)"
	}
}
