package analytics

import "time"

type Point struct {
	Period string  `json:"period"`
	Value  float64 `json:"value"`
	Count  uint64  `json:"count"`
}

type Funnel struct {
	Created   uint64 `json:"created"`
	Paid      uint64 `json:"paid"`
	Completed uint64 `json:"completed"`
	Cancelled uint64 `json:"cancelled"`
}

type Conversion struct {
	CreatedToPaid      float64 `json:"created_to_paid"`
	CreatedToCancelled float64 `json:"created_to_cancelled"`
}

type UsersReport struct {
	Total  uint64  `json:"total"`
	Series []Point `json:"series"`
}

type OrdersReport struct {
	Funnel       Funnel     `json:"funnel"`
	Conversion   Conversion `json:"conversion"`
	Revenue      float64    `json:"revenue"`
	AverageCheck float64    `json:"average_check"`
	Series       []Point    `json:"series"`
}

type SearchRow struct {
	Query      string  `json:"query"`
	Searches   uint64  `json:"searches"`
	AvgResults float64 `json:"avg_results,omitempty"`
}

type SearchReport struct {
	Popular        []SearchRow `json:"popular"`
	WithoutResults []SearchRow `json:"without_results"`
}

type Range struct {
	From        time.Time
	To          time.Time
	Granularity string
}

// BuildFunnel считает воронку created → paid/completed → cancelled.
// completed совпадает с paid: в order-service оплата и есть завершение заказа.
func BuildFunnel(created, paid, cancelled uint64) Funnel {
	return Funnel{
		Created:   created,
		Paid:      paid,
		Completed: paid,
		Cancelled: cancelled,
	}
}

func BuildConversion(created, paid, cancelled uint64) Conversion {
	if created == 0 {
		return Conversion{}
	}

	return Conversion{
		CreatedToPaid:      float64(paid) / float64(created),
		CreatedToCancelled: float64(cancelled) / float64(created),
	}
}

func AverageCheck(revenue float64, paid uint64) float64 {
	if paid == 0 {
		return 0
	}

	return revenue / float64(paid)
}

func NormalizeRange(from, to time.Time, granularity string) Range {
	if to.IsZero() {
		to = time.Now().UTC()
	}
	if from.IsZero() || !from.Before(to) {
		from = to.AddDate(0, 0, -30)
	}

	switch granularity {
	case "week", "month":
	default:
		granularity = "day"
	}

	return Range{From: from.UTC(), To: to.UTC(), Granularity: granularity}
}

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
