"use client";

import { FormEvent, Suspense, useState } from "react";
import Link from "next/link";
import { useParams, useSearchParams } from "next/navigation";
import { apiFetch, readError } from "@/app/lib/api";

function ResetPasswordForm() {
  const params = useParams<{ token: string }>();
  const searchParams = useSearchParams();
  const token = params.token;
  const [email, setEmail] = useState(searchParams.get("email") ?? "");
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");
  const [pending, setPending] = useState(false);

  async function onSubmit(event: FormEvent) {
    event.preventDefault();
    setError("");
    setMessage("");
    setPending(true);

    const response = await apiFetch("/api/auth/reset-password", {
      method: "POST",
      body: JSON.stringify({
        token,
        email,
        password,
        password_confirmation: passwordConfirmation,
      }),
    });

    setPending(false);

    if (!response.ok) {
      setError(await readError(response));
      return;
    }

    setMessage("Пароль обновлён. Можно войти.");
  }

  return (
    <main className="flex min-h-screen items-center justify-center px-4">
      <form onSubmit={onSubmit} className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
        <p className="text-sm font-medium text-slate-500">InnoTrainne</p>
        <h1 className="mt-2 text-2xl font-semibold">Новый пароль</h1>

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
        {message ? (
          <p className="mt-4 text-sm text-emerald-700">
            {message}{" "}
            <Link href="/login" className="font-medium text-slate-900">
              Войти
            </Link>
          </p>
        ) : null}

        <button
          type="submit"
          disabled={pending}
          className="mt-6 w-full rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white disabled:opacity-60"
        >
          {pending ? "Сохраняем..." : "Сохранить пароль"}
        </button>
      </form>
    </main>
  );
}

export default function ResetPasswordPage() {
  return (
    <Suspense fallback={<main className="p-8 text-sm text-slate-500">Загрузка...</main>}>
      <ResetPasswordForm />
    </Suspense>
  );
}
