package analytics

import (
	"errors"
	"strconv"
	"time"

	"github.com/shopspring/decimal"
)

// Money is a decimal amount encoded as a JSON number, not a float64.
type Money struct {
	decimal.Decimal
}

func NewMoney(value decimal.Decimal) Money {
	return Money{Decimal: value.Round(2)}
}

func (m Money) MarshalJSON() ([]byte, error) {
	return []byte(m.Decimal.Round(2).StringFixed(2)), nil
}

func (m *Money) UnmarshalJSON(data []byte) error {
	if m == nil {
		return errors.New("nil money")
	}
	if err := m.Decimal.UnmarshalJSON(data); err != nil {
		return err
	}
	m.Decimal = m.Decimal.Round(2)

	return nil
}

type Point struct {
	Period string `json:"period"`
	Count  uint64 `json:"count"`
}

type Funnel struct {
	Created   uint64 `json:"created"`
	Paid      uint64 `json:"paid"`
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
	Revenue      Money      `json:"revenue"`
	AverageCheck Money      `json:"average_check"`
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

// BuildFunnel — объёмы заказов в окне: создано, оплачено, отменено.
func BuildFunnel(created, paid, cancelled uint64) Funnel {
	return Funnel{Created: created, Paid: paid, Cancelled: cancelled}
}

// BuildConversion считает доли когорты заказов, созданных в этом окне.
// paid и cancelled — сколько из этих заказов оплачено и отменено в том же окне.
// Доля равна 0, если когорта пустая, и не превышает 1.
func BuildConversion(created, paid, cancelled uint64) Conversion {
	return Conversion{
		CreatedToPaid:      cappedRatio(paid, created),
		CreatedToCancelled: cappedRatio(cancelled, created),
	}
}

func cappedRatio(part, whole uint64) float64 {
	if whole == 0 || part == 0 {
		return 0
	}
	if part >= whole {
		return 1
	}

	return float64(part) / float64(whole)
}

// NetRevenue — сумма оплат минус сумма возвратов за одно окно.
func NetRevenue(paid, refunded decimal.Decimal) decimal.Decimal {
	return paid.Sub(refunded).Round(2)
}

// AverageCheck — чистая выручка на один оплаченный заказ в окне.
func AverageCheck(revenue decimal.Decimal, paid uint64) decimal.Decimal {
	if paid == 0 {
		return decimal.Zero
	}
	count, err := decimal.NewFromString(strconv.FormatUint(paid, 10))
	if err != nil {
		return decimal.Zero
	}

	return revenue.Div(count).Round(2)
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
