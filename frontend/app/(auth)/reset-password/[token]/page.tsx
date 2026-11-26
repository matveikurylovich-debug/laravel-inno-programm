"use client";

import { Suspense, useState, type FormEvent } from "react";
import Link from "next/link";
import { useParams, useSearchParams } from "next/navigation";
import Brand from "@/app/components/Brand";
import { resetPasswordRequest } from "@/app/lib/api/auth";

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

    try {
      await resetPasswordRequest({
        token,
        email,
        password,
        password_confirmation: passwordConfirmation,
      });
      setMessage("Пароль обновлён. Можно войти.");
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
        <h1 className="auth-title">Новый пароль</h1>

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

        {error ? (
          <p role="alert" className="alert alert-error mt-4">
            {error}
          </p>
        ) : null}
        {message ? (
          <p className="alert alert-success mt-4">
            {message}{" "}
            <Link href="/login" className="link">
              Войти
            </Link>
          </p>
        ) : null}

        <button type="submit" disabled={pending} className="btn btn-primary btn-block mt-6">
          {pending ? "Сохраняем..." : "Сохранить пароль"}
        </button>
      </form>
    </main>
  );
}

export default function ResetPasswordPage() {
  return (
    <Suspense fallback={<main className="state-screen">Загрузка...</main>}>
      <ResetPasswordForm />
    </Suspense>
  );
}
