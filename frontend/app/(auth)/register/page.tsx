"use client";

import { FormEvent, useState } from "react";
import Link from "next/link";
import Brand from "@/app/components/Brand";
import { destinationFor, persistAuth } from "@/app/lib/auth";
import { apiFetch, readError } from "@/app/lib/api";
import { formatBelarusPhone } from "@/app/lib/phone";
import type { AuthResponse } from "@/app/lib/types";

export default function RegisterPage() {
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [phone, setPhone] = useState("");
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [error, setError] = useState("");
  const [pending, setPending] = useState(false);

  async function onSubmit(event: FormEvent) {
    event.preventDefault();
    setError("");
    setPending(true);

    const response = await apiFetch("/api/auth/register", {
      method: "POST",
      body: JSON.stringify({
        name,
        email,
        phone,
        password,
        password_confirmation: passwordConfirmation,
      }),
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

  return (
    <main className="auth-shell">
      <form onSubmit={onSubmit} className="auth-card">
        <Brand />
        <h1 className="auth-title">Регистрация</h1>
        <p className="auth-hint">Новый аккаунт получает роль покупателя.</p>

        <label className="field mt-6">
          Имя
          <input
            required
            value={name}
            onChange={(event) => setName(event.target.value)}
            className="input"
          />
        </label>

        <label className="field mt-4">
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
          Телефон
          <input
            type="tel"
            required
            value={phone}
            placeholder="+375 (29) 123-45-67"
            onChange={(event) => setPhone(formatBelarusPhone(event.target.value))}
            className="input"
          />
        </label>

        <label className="field mt-4">
          Пароль
          <input
            type="password"
            required
            minLength={8}
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            className="input"
          />
        </label>

        <label className="field mt-4">
          Повторите пароль
          <input
            type="password"
            required
            value={passwordConfirmation}
            onChange={(event) => setPasswordConfirmation(event.target.value)}
            className="input"
          />
        </label>

        {error ? <p className="alert alert-error mt-4">{error}</p> : null}

        <button
          type="submit"
          disabled={pending}
          className="btn btn-primary btn-block mt-6"
        >
          {pending ? "Создаём аккаунт..." : "Зарегистрироваться"}
        </button>

        <p className="mt-5 text-center text-sm text-slate-500">
          Уже есть аккаунт?{" "}
          <Link href="/login" className="link">
            Войти
          </Link>
        </p>
      </form>
    </main>
  );
}
