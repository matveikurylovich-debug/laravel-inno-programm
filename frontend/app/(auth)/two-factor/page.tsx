"use client";

import { FormEvent, Suspense, useState } from "react";
import Link from "next/link";
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
      <main className="flex min-h-screen items-center justify-center px-4">
        <p className="text-sm text-slate-600">
          Сначала войдите по паролю.{" "}
          <Link href="/login" className="font-medium text-slate-900">
            Ко входу
          </Link>
        </p>
      </main>
    );
  }

  return (
    <main className="flex min-h-screen items-center justify-center px-4">
      <form onSubmit={onSubmit} className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
        <p className="text-sm font-medium text-slate-500">InnoTrainne</p>
        <h1 className="mt-2 text-2xl font-semibold">Код из письма</h1>
        <p className="mt-1 text-sm text-slate-500">Шестизначный код действует 5 минут. Письмо в MailHog: localhost:8025.</p>

        <label className="mt-6 block text-sm font-medium">
          Код
          <input
            required
            inputMode="numeric"
            pattern="\d{6}"
            maxLength={6}
            value={code}
            onChange={(event) => setCode(event.target.value.replace(/\D/g, "").slice(0, 6))}
            className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900"
          />
        </label>

        {error ? <p className="mt-4 text-sm text-red-600">{error}</p> : null}
        {message ? <p className="mt-4 text-sm text-emerald-700">{message}</p> : null}

        <button
          type="submit"
          disabled={pending}
          className="mt-6 w-full rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white disabled:opacity-60"
        >
          {pending ? "Проверяем..." : "Подтвердить"}
        </button>

        <button type="button" onClick={() => void resend()} className="mt-3 w-full text-sm text-slate-600">
          Отправить код ещё раз
        </button>
      </form>
    </main>
  );
}

export default function TwoFactorPage() {
  return (
    <Suspense fallback={<main className="p-8 text-sm text-slate-500">Загрузка...</main>}>
      <TwoFactorForm />
    </Suspense>
  );
}
