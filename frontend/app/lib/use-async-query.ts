"use client";

import { useCallback, useEffect, useRef, useState } from "react";

type QueryResultState<T> = {
  key: string;
  data: T | null;
  error: string;
};

export type AsyncQueryResult<T> = {
  data: T | null;
  error: string;
  isLoading: boolean;
  reload: () => void;
  mutate: (updater: (previous: T | null) => T | null) => void;
};

function isAbortError(reason: unknown): boolean {
  if (reason instanceof DOMException && reason.name === "AbortError") {
    return true;
  }
  return reason instanceof Error && reason.name === "AbortError";
}

export function useAsyncQuery<T>(
  fetcher: (signal: AbortSignal) => Promise<T>,
  depsKey: string,
  options: { enabled?: boolean; fallbackError?: string; keepPreviousData?: boolean } = {},
): AsyncQueryResult<T> {
  const {
    enabled = true,
    fallbackError = "Запрос не выполнен",
    keepPreviousData = false,
  } = options;
  const [reloadNonce, setReloadNonce] = useState(0);
  const requestKey = enabled ? `${depsKey}::${reloadNonce}` : "__disabled__";

  const [state, setState] = useState<QueryResultState<T>>({
    key: "",
    data: null,
    error: "",
  });

  const fetcherRef = useRef(fetcher);
  useEffect(() => {
    fetcherRef.current = fetcher;
  });

  useEffect(() => {
    if (!enabled) {
      return;
    }

    const controller = new AbortController();
    let active = true;

    fetcherRef
      .current(controller.signal)
      .then((result) => {
        if (!active || controller.signal.aborted) {
          return;
        }
        setState({ key: requestKey, data: result, error: "" });
      })
      .catch((reason: unknown) => {
        if (!active || controller.signal.aborted || isAbortError(reason)) {
          return;
        }
        const message = reason instanceof Error && reason.message ? reason.message : fallbackError;
        setState((prev) => ({
          key: requestKey,
          data: keepPreviousData ? prev.data : prev.key === requestKey ? prev.data : null,
          error: message,
        }));
      });

    return () => {
      active = false;
      controller.abort();
    };
  }, [enabled, fallbackError, keepPreviousData, requestKey]);

  const reload = useCallback(() => {
    setReloadNonce((count) => count + 1);
  }, []);

  const mutate = useCallback(
    (updater: (previous: T | null) => T | null) => {
      setState((prev) => ({
        ...prev,
        key: requestKey,
        data: updater(prev.data),
      }));
    },
    [requestKey],
  );

  const fresh = enabled && state.key === requestKey;
  const isLoading = enabled && !fresh;
  const data = !enabled ? null : fresh || keepPreviousData ? state.data : null;
  const error = fresh ? state.error : "";

  return { data, error, isLoading, reload, mutate };
}
