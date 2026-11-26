"use client";

type PaginationProps = {
  currentPage: number;
  lastPage: number;
  onPageChange: (page: number) => void;
  disabled?: boolean;
};

export default function Pagination({
  currentPage,
  lastPage,
  onPageChange,
  disabled = false,
}: PaginationProps) {
  if (lastPage <= 1) {
    return null;
  }

  const safeCurrent = Math.max(1, Math.min(lastPage, currentPage));

  return (
    <nav
      aria-label="Постраничная навигация"
      className="mt-6 flex items-center justify-center gap-3 text-sm text-slate-600"
    >
      <button
        type="button"
        aria-label="Предыдущая страница"
        disabled={disabled || safeCurrent <= 1}
        onClick={() => onPageChange(safeCurrent - 1)}
        className="btn btn-secondary btn-sm"
      >
        Назад
      </button>
      <span className="min-w-20 text-center font-medium tabular-nums" aria-live="polite">
        {safeCurrent} / {lastPage}
      </span>
      <button
        type="button"
        aria-label="Следующая страница"
        disabled={disabled || safeCurrent >= lastPage}
        onClick={() => onPageChange(safeCurrent + 1)}
        className="btn btn-secondary btn-sm"
      >
        Вперёд
      </button>
    </nav>
  );
}
