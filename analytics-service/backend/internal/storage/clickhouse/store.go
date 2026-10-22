package clickhouse

import (
	"context"
	"fmt"
	"time"

	"analytics-service/internal/analytics"
	"analytics-service/internal/config"
	"analytics-service/internal/domain"

	ch "github.com/ClickHouse/clickhouse-go/v2"
	"github.com/google/uuid"
	"github.com/shopspring/decimal"
)

type Store struct {
	conn ch.Conn
}

func Open(cfg config.Config) (*Store, error) {
	conn, err := ch.Open(&ch.Options{
		Addr: []string{cfg.ClickHouseAddr},
		Auth: ch.Auth{
			Database: cfg.ClickHouseDB,
			Username: cfg.ClickHouseUser,
			Password: cfg.ClickHousePassword,
		},
		DialTimeout: 5 * time.Second,
	})
	if err != nil {
		return nil, err
	}

	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()
	if err := conn.Ping(ctx); err != nil {
		return nil, err
	}

	return &Store{conn: conn}, nil
}

func (s *Store) Insert(ctx context.Context, event domain.Event) error {
	switch event.Name {
	case domain.UserRegistered:
		return s.insertUser(ctx, event)
	case domain.OrderCreated, domain.OrderPaid, domain.OrderCancelled:
		return s.insertOrder(ctx, event)
	case domain.SearchPerformed:
		return s.insertSearch(ctx, event)
	default:
		return fmt.Errorf("unknown event %s", event.Name)
	}
}

func (s *Store) insertUser(ctx context.Context, event domain.Event) error {
	if event.UserID == nil {
		return fmt.Errorf("user.registered without user_id")
	}

	batch, err := s.conn.PrepareBatch(ctx, "INSERT INTO analytics.events_users (user_id, event_time)")
	if err != nil {
		return err
	}
	if err := batch.Append(*event.UserID, event.OccurredAt); err != nil {
		return err
	}

	return batch.Send()
}

func (s *Store) insertOrder(ctx context.Context, event domain.Event) error {
	status, err := event.OrderStatus()
	if err != nil {
		return err
	}
	orderID, err := uuid.Parse(event.OrderID)
	if err != nil {
		return fmt.Errorf("order_id: %w", err)
	}
	if event.UserID == nil {
		return fmt.Errorf("order event without user_id")
	}

	batch, err := s.conn.PrepareBatch(ctx, "INSERT INTO analytics.events_orders (order_id, user_id, store_id, status, total_amount, event_time)")
	if err != nil {
		return err
	}
	amount := decimal.NewFromFloat(event.TotalAmount).Round(2)
	if err := batch.Append(orderID, *event.UserID, event.StoreID, status, amount, event.OccurredAt); err != nil {
		return err
	}

	return batch.Send()
}

func (s *Store) insertSearch(ctx context.Context, event domain.Event) error {
	batch, err := s.conn.PrepareBatch(ctx, "INSERT INTO analytics.events_search (query, results_count, took_ms, filters_json, user_id, event_time)")
	if err != nil {
		return err
	}
	if err := batch.Append(event.Query, event.ResultsCount, event.TookMS, event.FiltersJSON, event.UserID, event.OccurredAt); err != nil {
		return err
	}

	return batch.Send()
}

func (s *Store) Users(ctx context.Context, rng analytics.Range) (analytics.UsersReport, error) {
	var total uint64
	if err := s.conn.QueryRow(ctx, `
		SELECT count()
		FROM analytics.events_users
		WHERE event_time >= ? AND event_time < ?
	`, rng.From, rng.To).Scan(&total); err != nil {
		return analytics.UsersReport{}, err
	}

	rows, err := s.conn.Query(ctx, fmt.Sprintf(`
		SELECT %s AS period, count() AS registrations
		FROM analytics.events_users
		WHERE event_time >= ? AND event_time < ?
		GROUP BY period
		ORDER BY period
	`, analytics.PeriodSQL(rng.Granularity)), rng.From, rng.To)
	if err != nil {
		return analytics.UsersReport{}, err
	}
	defer rows.Close()

	series := make([]analytics.Point, 0)
	for rows.Next() {
		var period time.Time
		var count uint64
		if err := rows.Scan(&period, &count); err != nil {
			return analytics.UsersReport{}, err
		}
		series = append(series, analytics.Point{Period: period.UTC().Format(time.RFC3339), Value: float64(count), Count: count})
	}

	return analytics.UsersReport{Total: total, Series: series}, rows.Err()
}

func (s *Store) Orders(ctx context.Context, rng analytics.Range) (analytics.OrdersReport, error) {
	rows, err := s.conn.Query(ctx, `
		SELECT status, count()
		FROM analytics.events_orders
		WHERE event_time >= ? AND event_time < ?
		GROUP BY status
	`, rng.From, rng.To)
	if err != nil {
		return analytics.OrdersReport{}, err
	}
	defer rows.Close()

	var created, paid, cancelled uint64
	for rows.Next() {
		var status string
		var count uint64
		if err := rows.Scan(&status, &count); err != nil {
			return analytics.OrdersReport{}, err
		}
		switch status {
		case "created":
			created = count
		case "paid":
			paid = count
		case "cancelled":
			cancelled = count
		}
	}
	if err := rows.Err(); err != nil {
		return analytics.OrdersReport{}, err
	}

	var revenue decimal.Decimal
	if err := s.conn.QueryRow(ctx, `
		SELECT ifNull(sum(total_amount), toDecimal64(0, 2))
		FROM analytics.events_orders
		WHERE status = 'paid' AND event_time >= ? AND event_time < ?
	`, rng.From, rng.To).Scan(&revenue); err != nil {
		return analytics.OrdersReport{}, err
	}
	revenueFloat, _ := revenue.Float64()

	seriesRows, err := s.conn.Query(ctx, fmt.Sprintf(`
		SELECT %s AS period, sum(total_amount) AS revenue, count() AS orders
		FROM analytics.events_orders
		WHERE status = 'paid' AND event_time >= ? AND event_time < ?
		GROUP BY period
		ORDER BY period
	`, analytics.PeriodSQL(rng.Granularity)), rng.From, rng.To)
	if err != nil {
		return analytics.OrdersReport{}, err
	}
	defer seriesRows.Close()

	series := make([]analytics.Point, 0)
	for seriesRows.Next() {
		var period time.Time
		var amount decimal.Decimal
		var count uint64
		if err := seriesRows.Scan(&period, &amount, &count); err != nil {
			return analytics.OrdersReport{}, err
		}
		value, _ := amount.Float64()
		series = append(series, analytics.Point{Period: period.UTC().Format(time.RFC3339), Value: value, Count: count})
	}

	return analytics.OrdersReport{
		Funnel:       analytics.BuildFunnel(created, paid, cancelled),
		Conversion:   analytics.BuildConversion(created, paid, cancelled),
		Revenue:      revenueFloat,
		AverageCheck: analytics.AverageCheck(revenueFloat, paid),
		Series:       series,
	}, seriesRows.Err()
}

func (s *Store) Search(ctx context.Context, rng analytics.Range) (analytics.SearchReport, error) {
	popular, err := s.searchRows(ctx, `
		SELECT query, count() AS searches, avg(results_count) AS avg_results
		FROM analytics.events_search
		WHERE event_time >= ? AND event_time < ? AND query != ''
		GROUP BY query
		ORDER BY searches DESC
		LIMIT 20
	`, rng, true)
	if err != nil {
		return analytics.SearchReport{}, err
	}

	empty, err := s.searchRows(ctx, `
		SELECT query, count() AS searches, toFloat64(0) AS avg_results
		FROM analytics.events_search
		WHERE event_time >= ? AND event_time < ? AND query != '' AND results_count = 0
		GROUP BY query
		ORDER BY searches DESC
		LIMIT 20
	`, rng, false)
	if err != nil {
		return analytics.SearchReport{}, err
	}

	return analytics.SearchReport{Popular: popular, WithoutResults: empty}, nil
}

func (s *Store) searchRows(ctx context.Context, query string, rng analytics.Range, withAverage bool) ([]analytics.SearchRow, error) {
	rows, err := s.conn.Query(ctx, query, rng.From, rng.To)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	result := make([]analytics.SearchRow, 0)
	for rows.Next() {
		var row analytics.SearchRow
		if err := rows.Scan(&row.Query, &row.Searches, &row.AvgResults); err != nil {
			return nil, err
		}
		if !withAverage {
			row.AvgResults = 0
		}
		result = append(result, row)
	}

	return result, rows.Err()
}
