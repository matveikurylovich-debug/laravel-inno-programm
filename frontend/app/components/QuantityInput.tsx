"use client";

import { useId, useState, type KeyboardEvent } from "react";
import { MAX_QUANTITY, MIN_QUANTITY, clampQuantity, quantityDraftError } from "@/app/lib/quantity";

export { MAX_QUANTITY, MIN_QUANTITY, clampQuantity, quantityDraftError };

type QuantityInputProps = {
  value: number;
  onChange: (nextQuantity: number) => void;
  min?: number;
  max?: number;
  label?: string;
  ariaLabel?: string;
  disabled?: boolean;
  className?: string;
};

export default function QuantityInput({
  value,
  onChange,
  min = MIN_QUANTITY,
  max = MAX_QUANTITY,
  label,
  ariaLabel = "Количество",
  disabled = false,
  className = "input w-20",
}: QuantityInputProps) {
  const inputId = useId();
  const [draft, setDraft] = useState<string | null>(null);
  const [error, setError] = useState("");

  const displayed = draft ?? String(clampQuantity(value, min, max));

  function commit(rawDraft: string) {
    const message = quantityDraftError(rawDraft, min, max);
    setDraft(null);
    if (message) {
      setError(message);
      return;
    }

    setError("");
    const next = Number(rawDraft.trim());
    if (next !== value) {
      onChange(next);
    }
  }

  function handleKeyDown(event: KeyboardEvent<HTMLInputElement>) {
    if (event.key === "Enter") {
      event.preventDefault();
      commit(draft ?? String(value));
      event.currentTarget.blur();
    } else if (event.key === "Escape") {
      setDraft(null);
    }
  }

  const inputElement = (
    <input
      id={inputId}
      type="text"
      inputMode="numeric"
      pattern="[0-9]*"
      aria-label={label ? undefined : ariaLabel}
      disabled={disabled}
      value={displayed}
      onChange={(event) => {
        setError("");
        setDraft(event.target.value);
      }}
      onBlur={(event) => commit(event.target.value)}
      onKeyDown={handleKeyDown}
      className={label ? `${className} mt-1` : className}
    />
  );

  const field = label ? (
    <label htmlFor={inputId} className="text-sm text-slate-500">
      {label}
      {inputElement}
    </label>
  ) : (
    inputElement
  );

  return (
    <div>
      {field}
      {error ? (
        <p role="alert" className="mt-1 text-xs text-rose-600">
          {error}
        </p>
      ) : null}
    </div>
  );
}
