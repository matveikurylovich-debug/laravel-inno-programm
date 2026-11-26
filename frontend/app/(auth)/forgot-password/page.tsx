"use client";

import { useState, type FormEvent } from "react";
import Link from "next/link";
import Brand from "@/app/components/Brand";
import { forgotPasswordRequest } from "@/app/lib/api/auth";

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

    try {
      await forgotPasswordRequest({ email });
      setMessage("Если аккаунт существует, ссылка отправлена на почту.");
    } catch (reason: unknown) {
      setError(reason instanceof Error ? reason.message : "Запрос не выполнен");
    } finally {
      setPending(false);
    }
  }

  return (
    <main className="auth-shell">
      <form
        onSubmit={(event) => {
          void onSubmit(event);
        }}
        className="auth-card"
      >
        <Brand />
        <h1 className="auth-title">Сброс пароля</h1>
        <p className="auth-hint">
          Отправим ссылку для восстановления пароля на вашу электронную почту.
        </p>

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

        {error ? (
          <p role="alert" className="alert alert-error mt-4">
            {error}
          </p>
        ) : null}
        {message ? <p className="alert alert-success mt-4">{message}</p> : null}

        <button type="submit" disabled={pending} className="btn btn-primary btn-block mt-6">
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
