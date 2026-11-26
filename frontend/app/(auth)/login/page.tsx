"use client";

import { Suspense, useEffect, useState, type FormEvent } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import Brand from "@/app/components/Brand";
import { loginRequest } from "@/app/lib/api/auth";
import { destinationFor } from "@/app/lib/auth";
import { rememberChallengeId } from "@/app/lib/two-factor-challenge";
import { useSession } from "@/app/providers/SessionProvider";

function LoginForm() {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const router = useRouter();
  const searchParams = useSearchParams();
  const nextParam = searchParams.get("next");
  const { user, status } = useSession();
  const [error, setError] = useState("");
  const [pending, setPending] = useState(false);

  useEffect(() => {
    if (status === "authenticated" && user) {
      router.replace(destinationFor(user));
    }
  }, [router, status, user]);

  async function onSubmit(event: FormEvent) {
    event.preventDefault();
    setError("");
    setPending(true);

    try {
      const data = await loginRequest({ email, password });
      rememberChallengeId(data.challenge_id);
      const target = nextParam
        ? `/two-factor?next=${encodeURIComponent(nextParam)}`
        : "/two-factor";
      router.push(target);
    } catch (reason: unknown) {
      setError(reason instanceof Error ? reason.message : "Запрос не выполнен");
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

        {error ? (
          <p role="alert" className="alert alert-error mt-4">
            {error}
          </p>
        ) : null}

        <button type="submit" disabled={pending} className="btn btn-primary btn-block mt-6">
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

export default function LoginPage() {
  return (
    <Suspense fallback={<main className="state-screen">Загрузка...</main>}>
      <LoginForm />
    </Suspense>
  );
}
