"use client";

import { FormEvent, Suspense, useState } from "react";
import Link from "next/link";
import Brand from "@/app/components/Brand";
import { useSearchParams } from "next/navigation";
import { apiFetch, readError } from "@/app/lib/api";
import { destinationFor, persistAuth } from "@/app/lib/auth";
import type { AuthResponse } from "@/app/lib/types";

function TwoFactorForm() {
  const searchParams = useSearchParams();
  const userId = Number(searchParams.get("user_id") ?? "");
  const [code, setCode] = useState("");
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");
  const [pending, setPending] = useState(false);

  async function onSubmit(event: FormEvent) {
    event.preventDefault();
    setError("");
    setPending(true);

    const response = await apiFetch("/api/auth/two-factor/verify", {
      method: "POST",
      body: JSON.stringify({ user_id: userId, code }),
    });

    if (!response.ok) {
      setError(await readError(response));
      setPending(false);
      return;
    }

    const data = (await response.json()) as AuthResponse;
    persistAuth(data);
    window.location.href = destinationFor(data.user.role);
  }

  async function resend() {
    setError("");
    setMessage("");
    const response = await apiFetch("/api/auth/two-factor/resend", {
      method: "POST",
      body: JSON.stringify({ user_id: userId }),
    });

    if (!response.ok) {
      setError(await readError(response));
      return;
    }

    setMessage("Новый код отправлен на почту.");
  }

  if (!userId) {
    return (
      <main className="auth-shell">
        <p className="auth-card text-center text-sm text-slate-600">
          Сначала войдите по паролю.{" "}
          <Link href="/login" className="link">
            Ко входу
          </Link>
        </p>
      </main>
    );
  }

  return (
    <main className="auth-shell">
      <form onSubmit={onSubmit} className="auth-card">
        <Brand />
        <h1 className="auth-title">Код из письма</h1>
        <p className="auth-hint">Шестизначный код действует 5 минут. Письмо в MailHog: localhost:8025.</p>

        <label className="field mt-6">
          Код
          <input
            required
            inputMode="numeric"
            pattern="\d{6}"
            maxLength={6}
            value={code}
            onChange={(event) => setCode(event.target.value.replace(/\D/g, "").slice(0, 6))}
            className="input text-center font-mono text-2xl tracking-[0.5em]"
          />
        </label>

        {error ? <p className="alert alert-error mt-4">{error}</p> : null}
        {message ? <p className="alert alert-success mt-4">{message}</p> : null}

        <button
          type="submit"
          disabled={pending}
          className="btn btn-primary btn-block mt-6"
        >
          {pending ? "Проверяем..." : "Подтвердить"}
        </button>

        <button type="button" onClick={() => void resend()} className="btn btn-ghost btn-block mt-3">
          Отправить код ещё раз
        </button>
      </form>
    </main>
  );
}

export default function TwoFactorPage() {
  return (
    <Suspense fallback={<main className="state-screen">Загрузка...</main>}>
      <TwoFactorForm />
    </Suspense>
  );
}
