"use client";

import { FormEvent, useState } from "react";
import Link from "next/link";
import Brand from "@/app/components/Brand";
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
    <main className="auth-shell">
      <form onSubmit={onSubmit} className="auth-card">
        <Brand />
        <h1 className="auth-title">Вход</h1>
        <p className="auth-hint">После пароля отправим код подтверждения на почту.</p>

        <label className="field mt-6">
          Email
          <input
            type="email"
            required
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            className="input"
          />
        </label>

        <label className="field mt-4">
          Пароль
          <input
            type="password"
            required
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            className="input"
          />
        </label>

        {error ? <p className="alert alert-error mt-4">{error}</p> : null}

        <button
          type="submit"
          disabled={pending}
          className="btn btn-primary btn-block mt-6"
        >
          {pending ? "Проверяем..." : "Войти"}
        </button>

        <p className="mt-5 text-center text-sm text-slate-500">
          <Link href="/forgot-password" className="link">
            Забыли пароль?
          </Link>
        </p>

        <p className="mt-2 text-center text-sm text-slate-500">
          Нет аккаунта?{" "}
          <Link href="/register" className="link">
            Зарегистрироваться
          </Link>
        </p>
      </form>
    </main>
  );
}
