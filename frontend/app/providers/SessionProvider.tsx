"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";
import { fetchMeSession, type MeResult } from "@/app/lib/api/auth";
import { resetCartOnLogout, setCartUser } from "@/app/lib/cart";
import type { AuthUser } from "@/app/lib/types";

export type SessionStatus = "loading" | "authenticated" | "guest" | "unavailable";

export type SessionContextValue = {
  user: AuthUser | null;
  status: SessionStatus;
  error: string | null;
  refresh: () => Promise<AuthUser | null>;
  clear: () => void;
};

const SessionContext = createContext<SessionContextValue | null>(null);

let sessionProbeInFlight: Promise<MeResult> | null = null;

function probeSessionOnce(): Promise<MeResult> {
  if (!sessionProbeInFlight) {
    sessionProbeInFlight = fetchMeSession().finally(() => {
      sessionProbeInFlight = null;
    });
  }
  return sessionProbeInFlight;
}

type InternalSessionState = {
  user: AuthUser | null;
  status: SessionStatus;
  error: string | null;
};

export function SessionProvider({ children }: { children: ReactNode }) {
  const [state, setState] = useState<InternalSessionState>({
    user: null,
    status: "loading",
    error: null,
  });

  const applyResult = useCallback((result: MeResult): AuthUser | null => {
    if (result.status === "authenticated") {
      setCartUser(result.user.id);
      setState({
        user: result.user,
        status: "authenticated",
        error: null,
      });
      return result.user;
    }

    if (result.status === "guest") {
      setCartUser(null);
      setState({
        user: null,
        status: "guest",
        error: null,
      });
      return null;
    }

    setState({
      user: null,
      status: "unavailable",
      error: result.error,
    });
    return null;
  }, []);

  const refresh = useCallback(async (): Promise<AuthUser | null> => {
    setState((prev) =>
      prev.user
        ? { ...prev, error: null }
        : {
            ...prev,
            status: "loading",
            error: null,
          },
    );
    const result = await probeSessionOnce();
    return applyResult(result);
  }, [applyResult]);

  const clear = useCallback(() => {
    resetCartOnLogout();
    setState({
      user: null,
      status: "guest",
      error: null,
    });
  }, []);

  useEffect(() => {
    let active = true;

    void probeSessionOnce().then((result) => {
      if (!active) {
        return;
      }
      applyResult(result);
    });

    return () => {
      active = false;
    };
  }, [applyResult]);

  const value = useMemo<SessionContextValue>(
    () => ({
      user: state.user,
      status: state.status,
      error: state.error,
      refresh,
      clear,
    }),
    [clear, refresh, state.error, state.status, state.user],
  );

  return (
    <SessionContext.Provider value={value}>
      {state.status === "unavailable" ? (
        <div
          role="alert"
          className="border-b border-rose-200 bg-rose-50 px-4 py-2.5 text-sm text-rose-800"
        >
          <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3">
            <span>{state.error ?? "Сервис временно недоступен"}</span>
            <button
              type="button"
              className="btn btn-secondary btn-sm"
              onClick={() => {
                void refresh();
              }}
            >
              Повторить
            </button>
          </div>
        </div>
      ) : null}
      {children}
    </SessionContext.Provider>
  );
}

export function useSession(): SessionContextValue {
  const context = useContext(SessionContext);
  if (!context) {
    throw new Error("useSession должен вызываться внутри SessionProvider");
  }
  return context;
}

export function SessionUnavailableScreen({
  message,
  onRetry,
}: {
  message?: string | null;
  onRetry: () => void;
}) {
  return (
    <main className="state-screen">
      <div role="alert" className="card max-w-md space-y-4 p-6 text-center">
        <p className="text-base font-semibold text-slate-900">Сервис временно недоступен</p>
        <p className="text-sm text-slate-600">
          {message && message !== "Сервис временно недоступен"
            ? message
            : "Не удалось проверить сессию пользователя. Попробуйте ещё раз."}
        </p>
        <button type="button" className="btn btn-primary" onClick={onRetry}>
          Повторить
        </button>
      </div>
    </main>
  );
}
