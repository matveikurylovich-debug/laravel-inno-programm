"use client";

import { FormEvent, useState } from "react";
import Link from "next/link";
import Brand from "@/app/components/Brand";
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
    <main className="auth-shell">
      <form onSubmit={onSubmit} className="auth-card">
        <Brand />
        <h1 className="auth-title">Сброс пароля</h1>
        <p className="auth-hint">Письмо со ссылкой придёт в MailHog.</p>

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

        {error ? <p className="alert alert-error mt-4">{error}</p> : null}
        {message ? <p className="alert alert-success mt-4">{message}</p> : null}

        <button
          type="submit"
          disabled={pending}
          className="btn btn-primary btn-block mt-6"
        >
          {pending ? "Отправляем..." : "Отправить ссылку"}
        </button>

        <p className="mt-5 text-center text-sm text-slate-500">
          <Link href="/login" className="link">
            Ко входу
          </Link>
        </p>
      </form>
    </main>
  );
}
