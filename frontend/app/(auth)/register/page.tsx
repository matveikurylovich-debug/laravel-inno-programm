"use client";

import { FormEvent, useState } from "react";
import Link from "next/link";
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
    <main className="flex min-h-screen items-center justify-center px-4">
      <form onSubmit={onSubmit} className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
        <p className="text-sm font-medium text-slate-500">InnoTrainne</p>
        <h1 className="mt-2 text-2xl font-semibold">Регистрация</h1>
        <p className="mt-1 text-sm text-slate-500">Новый аккаунт получает роль покупателя.</p>

        <label className="mt-6 block text-sm font-medium">
          Имя
          <input
            required
            value={name}
            onChange={(event) => setName(event.target.value)}
            className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900"
          />
        </label>

        <label className="mt-4 block text-sm font-medium">
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
          Телефон
          <input
            type="tel"
            required
            value={phone}
            placeholder="+375 (29) 123-45-67"
            onChange={(event) => setPhone(formatBelarusPhone(event.target.value))}
            className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900"
          />
        </label>

        <label className="mt-4 block text-sm font-medium">
          Пароль
          <input
            type="password"
            required
            minLength={8}
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900"
          />
        </label>

        <label className="mt-4 block text-sm font-medium">
          Повторите пароль
          <input
            type="password"
            required
            value={passwordConfirmation}
            onChange={(event) => setPasswordConfirmation(event.target.value)}
            className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900"
          />
        </label>

        {error ? <p className="mt-4 text-sm text-red-600">{error}</p> : null}

        <button
          type="submit"
          disabled={pending}
          className="mt-6 w-full rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white disabled:opacity-60"
        >
          {pending ? "Создаём аккаунт..." : "Зарегистрироваться"}
        </button>

        <p className="mt-4 text-sm text-slate-500">
          Уже есть аккаунт?{" "}
          <Link href="/login" className="font-medium text-slate-900">
            Войти
          </Link>
        </p>
      </form>
    </main>
  );
}
