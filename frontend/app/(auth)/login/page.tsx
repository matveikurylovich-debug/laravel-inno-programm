"use client";

import { FormEvent, useState } from "react";
import Link from "next/link";
import { apiFetch, readError } from "@/app/lib/api";
import type { TwoFactorChallenge } from "@/app/lib/types";

export default function LoginPage() {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [pending, setPending] = useState(false);

  async function onSubmit(event: FormEvent) {
    event.preventDefault();
    setError("");
    setPending(true);

    const response = await apiFetch("/api/auth/login", {
      method: "POST",
      body: JSON.stringify({ email, password }),
    });

    if (!response.ok) {
      setError(await readError(response));
      setPending(false);
      return;
    }

    const data = (await response.json()) as TwoFactorChallenge;
    window.location.href = `/two-factor?user_id=${data.user_id}`;
  }

  return (
    <main className="flex min-h-screen items-center justify-center px-4">
      <form onSubmit={onSubmit} className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
        <p className="text-sm font-medium text-slate-500">InnoTrainne</p>
        <h1 className="mt-2 text-2xl font-semibold">Вход</h1>
        <p className="mt-1 text-sm text-slate-500">После пароля отправим код подтверждения на почту.</p>

        <label className="mt-6 block text-sm font-medium">
          Email
          <input
            type="email"
            required
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900"
          />
        </label>

        <label className="mt-4 block text-sm font-medium">
          Пароль
          <input
            type="password"
            required
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900"
          />
        </label>

        {error ? <p className="mt-4 text-sm text-red-600">{error}</p> : null}

        <button
          type="submit"
          disabled={pending}
          className="mt-6 w-full rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white disabled:opacity-60"
        >
          {pending ? "Проверяем..." : "Войти"}
        </button>

        <p className="mt-4 text-sm text-slate-500">
          <Link href="/forgot-password" className="font-medium text-slate-900">
            Забыли пароль?
          </Link>
        </p>

        <p className="mt-2 text-sm text-slate-500">
          Нет аккаунта?{" "}
          <Link href="/register" className="font-medium text-slate-900">
            Зарегистрироваться
          </Link>
        </p>
      </form>
    </main>
  );
}
