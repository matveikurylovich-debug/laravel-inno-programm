"use client";

import { FormEvent, useState } from "react";
import Link from "next/link";
import { apiFetch, readError } from "@/app/lib/api";

export default function ForgotPasswordPage() {
  const [email, setEmail] = useState("");
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");
  const [pending, setPending] = useState(false);

  async function onSubmit(event: FormEvent) {
    event.preventDefault();
    setError("");
    setMessage("");
    setPending(true);

    const response = await apiFetch("/api/auth/forgot-password", {
      method: "POST",
      body: JSON.stringify({ email }),
    });

    setPending(false);

    if (!response.ok) {
      setError(await readError(response));
      return;
    }

    setMessage("Если аккаунт существует, ссылка отправлена на почту.");
  }

  return (
    <main className="flex min-h-screen items-center justify-center px-4">
      <form onSubmit={onSubmit} className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
        <p className="text-sm font-medium text-slate-500">InnoTrainne</p>
        <h1 className="mt-2 text-2xl font-semibold">Сброс пароля</h1>
        <p className="mt-1 text-sm text-slate-500">Письмо со ссылкой придёт в MailHog.</p>

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

        {error ? <p className="mt-4 text-sm text-red-600">{error}</p> : null}
        {message ? <p className="mt-4 text-sm text-emerald-700">{message}</p> : null}

        <button
          type="submit"
          disabled={pending}
          className="mt-6 w-full rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white disabled:opacity-60"
        >
          {pending ? "Отправляем..." : "Отправить ссылку"}
        </button>

        <p className="mt-4 text-sm text-slate-500">
          <Link href="/login" className="font-medium text-slate-900">
            Ко входу
          </Link>
        </p>
      </form>
    </main>
  );
}
