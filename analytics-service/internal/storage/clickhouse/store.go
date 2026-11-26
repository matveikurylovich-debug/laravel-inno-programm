package clickhouse

import (
	"context"
	"fmt"
	"time"

	ch "github.com/ClickHouse/clickhouse-go/v2"
	"github.com/google/uuid"
	"github.com/shopspring/decimal"

	"analytics-service/internal/analytics"
	"analytics-service/internal/config"
	"analytics-service/internal/domain"
)

type Store struct {
	conn ch.Conn
}

func Open(ctx context.Context, cfg config.ClickHouse) (*Store, error) {
	conn, err := ch.Open(&ch.Options{
		Addr: []string{cfg.Addr},
		Auth: ch.Auth{
			Database: cfg.Database,
			Username: cfg.User,
			Password: cfg.Password,
		},
		DialTimeout: 5 * time.Second,
	})
	if err != nil {
		return nil, err
	}

	openCtx, cancel := context.WithTimeout(ctx, 5*time.Second)
	defer cancel()
	if err := conn.Ping(openCtx); err != nil {
		_ = conn.Close()
		return nil, err
	}

	store := &Store{conn: conn}
	if err := store.migrate(openCtx); err != nil {
		_ = conn.Close()
		return nil, err
	}

	return store, nil
}

func (s *Store) Close() error {
	return s.conn.Close()
}

func (s *Store) Ping(ctx context.Context) error {
	return s.conn.Ping(ctx)
}

func (s *Store) Insert(ctx context.Context, event domain.Event) error {
	return s.InsertMany(ctx, []domain.Event{event})
}

func (s *Store) InsertMany(ctx context.Context, events []domain.Event) error {
	events = dedupeEvents(events)
	var users []domain.UserRegistered
	var orders []domain.Event
	var searches []domain.SearchPerformed
	for _, event := range events {
		if err := event.Validate(); err != nil {
			return err
		}
		switch typed := event.(type) {
		case domain.UserRegistered:
			users = append(users, typed)
		case domain.OrderCreated, domain.OrderPaid, domain.OrderCancelled, domain.OrderRefunded:
			orders = append(orders, event)
		case domain.SearchPerformed:
			searches = append(searches, typed)
		default:
			return fmt.Errorf("%w: unknown event %s", domain.ErrInvalidEvent, event.Name())
		}
	}
	if err := s.insertUsers(ctx, users); err != nil {
		return err
	}
	if err := s.insertOrders(ctx, orders); err != nil {
		return err
	}

	return s.insertSearches(ctx, searches)
}

func (s *Store) insertUsers(ctx context.Context, events []domain.UserRegistered) error {
	if len(events) == 0 {
		return nil
	}
	batch, err := s.conn.PrepareBatch(ctx, "INSERT INTO analytics.events_users (event_id, user_id, event_time)")
	if err != nil {
		return fmt.Errorf("prepare users: %w", err)
	}
	for _, event := range events {
		if err := batch.Append(event.EventID, event.UserID, event.OccurredAt); err != nil {
			return fmt.Errorf("append user: %w", err)
		}
	}

	return batch.Send()
}

type orderRow struct {
	eventID  string
	orderID  string
	userID   uint64
	storeID  uint32
	status   string
	amount   decimal.Decimal
	refunded decimal.Decimal
	at       time.Time
}

func asOrderRow(event domain.Event) (orderRow, error) {
	switch typed := event.(type) {
	case domain.OrderCreated:
		return orderRow{typed.EventID, typed.OrderID, typed.UserID, typed.StoreID, "created", typed.TotalAmount, decimal.Zero, typed.OccurredAt}, nil
	case domain.OrderPaid:
		return orderRow{typed.EventID, typed.OrderID, typed.UserID, typed.StoreID, "paid", typed.TotalAmount, decimal.Zero, typed.OccurredAt}, nil
	case domain.OrderCancelled:
		return orderRow{typed.EventID, typed.OrderID, typed.UserID, typed.StoreID, "cancelled", typed.TotalAmount, typed.RefundedAmount, typed.OccurredAt}, nil
	case domain.OrderRefunded:
		return orderRow{typed.EventID, typed.OrderID, typed.UserID, typed.StoreID, "refunded", decimal.Zero, typed.RefundedAmount, typed.OccurredAt}, nil
	default:
		return orderRow{}, fmt.Errorf("%w: not an order event %s", domain.ErrInvalidEvent, event.Name())
	}
}

func (s *Store) insertOrders(ctx context.Context, events []domain.Event) error {
	if len(events) == 0 {
		return nil
	}
	batch, err := s.conn.PrepareBatch(ctx, "INSERT INTO analytics.events_orders (event_id, order_id, user_id, store_id, status, total_amount, refunded_amount, event_time)")
	if err != nil {
		return fmt.Errorf("prepare orders: %w", err)
	}
	for _, event := range events {
		row, err := asOrderRow(event)
		if err != nil {
			return err
		}
		orderID, err := uuid.Parse(row.orderID)
		if err != nil {
			return fmt.Errorf("%w: order_id: %w", domain.ErrInvalidEvent, err)
		}
		if err := batch.Append(row.eventID, orderID, row.userID, row.storeID, row.status, row.amount, row.refunded, row.at); err != nil {
			return fmt.Errorf("append order: %w", err)
		}
	}

	return batch.Send()
}

func (s *Store) insertSearches(ctx context.Context, events []domain.SearchPerformed) error {
	if len(events) == 0 {
		return nil
	}
	batch, err := s.conn.PrepareBatch(ctx, "INSERT INTO analytics.events_search (event_id, query, results_count, took_ms, filters_json, user_id, event_time)")
	if err != nil {
		return fmt.Errorf("prepare search: %w", err)
	}
	for _, event := range events {
		if err := batch.Append(event.EventID, event.Query, event.ResultsCount, event.TookMS, event.FiltersJSON, event.UserID, event.OccurredAt); err != nil {
			return fmt.Errorf("append search: %w", err)
		}
	}

	return batch.Send()
}

func dedupeEvents(events []domain.Event) []domain.Event {
	seen := make(map[string]int, len(events))
	unique := make([]domain.Event, 0, len(events))
	for _, event := range events {
		if index, ok := seen[event.ID()]; ok {
			unique[index] = event
			continue
		}
		seen[event.ID()] = len(unique)
		unique = append(unique, event)
	}

	return unique
}

func (s *Store) Users(ctx context.Context, rng analytics.Range) (analytics.UsersReport, error) {
	var total uint64
	if err := s.conn.QueryRow(ctx, `
		SELECT uniqExact(user_id)
		FROM analytics.events_users FINAL
		WHERE event_time >= ? AND event_time < ?
	`, rng.From, rng.To).Scan(&total); err != nil {
		return analytics.UsersReport{}, err
	}

	rows, err := s.conn.Query(ctx, usersSeriesSQL(rng.Granularity), rng.From, rng.To)
	if err != nil {
		return analytics.UsersReport{}, err
	}
	defer func() { _ = rows.Close() }()

	series := make([]analytics.Point, 0)
	for rows.Next() {
		var period time.Time
		var count uint64
		if err := rows.Scan(&period, &count); err != nil {
			return analytics.UsersReport{}, err
		}
		series = append(series, analytics.Point{Period: period.UTC().Format(time.RFC3339), Count: count})
	}

	return analytics.UsersReport{Total: total, Series: series}, rows.Err()
}

func usersSeriesSQL(granularity string) string {
	switch PeriodSQL(granularity) {
	case "toStartOfWeek(event_time)":
		return `
			SELECT toStartOfWeek(event_time) AS period, uniqExact(user_id) AS registrations
			FROM analytics.events_users FINAL
			WHERE event_time >= ? AND event_time < ?
			GROUP BY period
			ORDER BY period
		`
	case "toStartOfMonth(event_time)":
		return `
			SELECT toStartOfMonth(event_time) AS period, uniqExact(user_id) AS registrations
			FROM analytics.events_users FINAL
			WHERE event_time >= ? AND event_time < ?
			GROUP BY period
			ORDER BY period
		`
	default:
		return `
			SELECT toStartOfDay(event_time) AS period, uniqExact(user_id) AS registrations
			FROM analytics.events_users FINAL
			WHERE event_time >= ? AND event_time < ?
			GROUP BY period
			ORDER BY period
		`
	}
}

func (s *Store) Orders(ctx context.Context, rng analytics.Range) (analytics.OrdersReport, error) {
	rows, err := s.conn.Query(ctx, `
		SELECT status, uniqExact(order_id)
		FROM analytics.events_orders FINAL
		WHERE event_time >= ? AND event_time < ?
		GROUP BY status
	`, rng.From, rng.To)
	if err != nil {
		return analytics.OrdersReport{}, err
	}
	defer func() { _ = rows.Close() }()

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

	var cohortCreated, cohortPaid, cohortCancelled uint64
	if err := s.conn.QueryRow(ctx, `
		SELECT count(), countIf(has_paid = 1), countIf(has_cancelled = 1)
		FROM (
			SELECT
				max(status = 'created') AS has_created,
				max(status = 'paid') AS has_paid,
				max(status = 'cancelled') AS has_cancelled
			FROM analytics.events_orders FINAL
			WHERE event_time >= ? AND event_time < ?
			  AND status IN ('created', 'paid', 'cancelled')
			GROUP BY order_id
			HAVING has_created = 1
		) AS cohort
	`, rng.From, rng.To).Scan(&cohortCreated, &cohortPaid, &cohortCancelled); err != nil {
		return analytics.OrdersReport{}, err
	}

	var paidAmount, refundedAmount decimal.Decimal
	if err := s.conn.QueryRow(ctx, `
		SELECT
			ifNull(sumIf(total_amount, status = 'paid'), toDecimal64(0, 2)),
			ifNull(sumIf(refunded_amount, status IN ('cancelled', 'refunded')), toDecimal64(0, 2))
		FROM analytics.events_orders FINAL
		WHERE event_time >= ? AND event_time < ?
	`, rng.From, rng.To).Scan(&paidAmount, &refundedAmount); err != nil {
		return analytics.OrdersReport{}, err
	}
	net := analytics.NetRevenue(paidAmount, refundedAmount)

	return analytics.OrdersReport{
		Funnel:       analytics.BuildFunnel(created, paid, cancelled),
		Conversion:   analytics.BuildConversion(cohortCreated, cohortPaid, cohortCancelled),
		Revenue:      analytics.NewMoney(net),
		AverageCheck: analytics.NewMoney(analytics.AverageCheck(net, paid)),
	}, nil
}

func (s *Store) Search(ctx context.Context, rng analytics.Range) (analytics.SearchReport, error) {
	popular, err := s.searchRows(ctx, `
		SELECT query, uniqExact(event_id) AS searches, avg(results_count) AS avg_results
		FROM analytics.events_search FINAL
		WHERE event_time >= ? AND event_time < ? AND query != ''
		GROUP BY query
		ORDER BY searches DESC
		LIMIT 20
	`, rng, true)
	if err != nil {
		return analytics.SearchReport{}, err
	}

	empty, err := s.searchRows(ctx, `
		SELECT query, uniqExact(event_id) AS searches, toFloat64(0) AS avg_results
		FROM analytics.events_search FINAL
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
	defer func() { _ = rows.Close() }()

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

func (s *Store) migrate(ctx context.Context) error {
	ready, err := s.hasColumn(ctx, "events_users", "event_id")
	if err != nil {
		return err
	}
	if !ready {
		if err := s.migrateReplacing(ctx); err != nil {
			return err
		}
	}

	return s.ensureRefundColumn(ctx)
}

func (s *Store) migrateReplacing(ctx context.Context) error {
	statements := []string{
		`CREATE TABLE IF NOT EXISTS analytics.events_users_v2 (
			event_id String, user_id UInt64, event_time DateTime('UTC'), inserted_at DateTime DEFAULT now()
		) ENGINE = ReplacingMergeTree(inserted_at) PARTITION BY toYYYYMM(event_time) ORDER BY (user_id)`,
		`INSERT INTO analytics.events_users_v2 (event_id, user_id, event_time, inserted_at)
			SELECT '', user_id, event_time, event_time FROM analytics.events_users`,
		`RENAME TABLE analytics.events_users TO analytics.events_users_legacy, analytics.events_users_v2 TO analytics.events_users`,
		`CREATE TABLE IF NOT EXISTS analytics.events_orders_v2 (
			event_id String, order_id UUID, user_id UInt64, store_id UInt32, status LowCardinality(String),
			total_amount Decimal64(2), refunded_amount Decimal64(2) DEFAULT 0, event_time DateTime('UTC'), inserted_at DateTime DEFAULT now()
		) ENGINE = ReplacingMergeTree(inserted_at) PARTITION BY toYYYYMM(event_time) ORDER BY (status, order_id)`,
		`INSERT INTO analytics.events_orders_v2 (event_id, order_id, user_id, store_id, status, total_amount, event_time, inserted_at)
			SELECT '', order_id, user_id, store_id, status, total_amount, event_time, event_time FROM analytics.events_orders`,
		`RENAME TABLE analytics.events_orders TO analytics.events_orders_legacy, analytics.events_orders_v2 TO analytics.events_orders`,
		`CREATE TABLE IF NOT EXISTS analytics.events_search_v2 (
			event_id String, query String, results_count UInt32, took_ms UInt32, filters_json String,
			user_id Nullable(UInt64), event_time DateTime('UTC'), inserted_at DateTime DEFAULT now()
		) ENGINE = ReplacingMergeTree(inserted_at) PARTITION BY toYYYYMM(event_time) ORDER BY (toDate(event_time), event_id)`,
		`INSERT INTO analytics.events_search_v2 (event_id, query, results_count, took_ms, filters_json, user_id, event_time, inserted_at)
			SELECT '', query, results_count, took_ms, filters_json, user_id, event_time, event_time FROM analytics.events_search`,
		`RENAME TABLE analytics.events_search TO analytics.events_search_legacy, analytics.events_search_v2 TO analytics.events_search`,
	}
	for _, statement := range statements {
		if err := s.conn.Exec(ctx, statement); err != nil {
			return fmt.Errorf("clickhouse migration: %w", err)
		}
	}

	return nil
}

func (s *Store) ensureRefundColumn(ctx context.Context) error {
	exists, err := s.hasColumn(ctx, "events_orders", "refunded_amount")
	if err != nil || exists {
		return err
	}
	if err := s.conn.Exec(ctx, `ALTER TABLE analytics.events_orders ADD COLUMN IF NOT EXISTS refunded_amount Decimal64(2) DEFAULT 0`); err != nil {
		return fmt.Errorf("clickhouse migration: %w", err)
	}

	return nil
}

func (s *Store) hasColumn(ctx context.Context, table, column string) (bool, error) {
	var count uint64
	err := s.conn.QueryRow(ctx, `
		SELECT count()
		FROM system.columns
		WHERE database = currentDatabase() AND table = ? AND name = ?
	`, table, column).Scan(&count)
	if err != nil {
		return false, fmt.Errorf("schema lookup: %w", err)
	}

	return count > 0, nil
}
