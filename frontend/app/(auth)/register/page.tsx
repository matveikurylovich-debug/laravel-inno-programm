"use client";

import { Suspense, useEffect, useState, type FormEvent } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import Brand from "@/app/components/Brand";
import { registerRequest } from "@/app/lib/api/auth";
import { destinationFor } from "@/app/lib/auth";
import { registerFieldErrors, type RegisterField } from "@/app/lib/register-form";
import { rememberChallengeId } from "@/app/lib/two-factor-challenge";
import { applyPhoneChange, formatBelarusPhone } from "@/app/lib/phone";
import { useSession } from "@/app/providers/SessionProvider";

function RegisterForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const { user, status } = useSession();
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [phone, setPhone] = useState("");
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [fieldErrors, setFieldErrors] = useState<Partial<Record<RegisterField, string>>>({});
  const [error, setError] = useState("");
  const [pending, setPending] = useState(false);

  useEffect(() => {
    if (status === "authenticated" && user) {
      router.replace(destinationFor(user));
    }
  }, [router, status, user]);

  async function onSubmit(event: FormEvent) {
    event.preventDefault();
    const nextErrors = registerFieldErrors({
      name,
      email,
      phone,
      password,
      passwordConfirmation,
    });
    setFieldErrors(nextErrors);
    if (Object.keys(nextErrors).length > 0) {
      return;
    }

    setError("");
    setPending(true);

    try {
      const data = await registerRequest({
        name,
        email,
        phone,
        password,
        password_confirmation: passwordConfirmation,
      });
      rememberChallengeId(data.challenge_id);
      const nextParam = searchParams.get("next");
      router.push(nextParam ? `/two-factor?next=${encodeURIComponent(nextParam)}` : "/two-factor");
    } catch (reason: unknown) {
      setError(reason instanceof Error ? reason.message : "Запрос не выполнен");
      setPending(false);
    }
  }

  return (
    <main className="auth-shell">
      <form
        noValidate
        onSubmit={(event) => {
          void onSubmit(event);
        }}
        className="auth-card"
      >
        <Brand />
        <h1 className="auth-title">Регистрация</h1>
        <p className="auth-hint">
          Все поля обязательны. После анкеты отправим код подтверждения на почту.
        </p>

        <label className="field mt-6">
          <span>
            Имя <span className="text-rose-600">*</span>
          </span>
          <input
            value={name}
            aria-invalid={fieldErrors.name ? true : undefined}
            onChange={(event) => setName(event.target.value)}
            className="input"
          />
          {fieldErrors.name ? (
            <span role="alert" className="text-sm text-rose-600">
              {fieldErrors.name}
            </span>
          ) : null}
        </label>

        <label className="field mt-4">
          <span>
            Email <span className="text-rose-600">*</span>
          </span>
          <input
            type="email"
            value={email}
            aria-invalid={fieldErrors.email ? true : undefined}
            onChange={(event) => setEmail(event.target.value)}
            className="input"
          />
          {fieldErrors.email ? (
            <span role="alert" className="text-sm text-rose-600">
              {fieldErrors.email}
            </span>
          ) : null}
        </label>

        <label className="field mt-4">
          <span>
            Телефон <span className="text-rose-600">*</span>
          </span>
          <input
            type="tel"
            value={phone}
            aria-invalid={fieldErrors.phone ? true : undefined}
            placeholder="+375 (29) 123-45-67"
            onChange={(event) => setPhone(applyPhoneChange(phone, event.target.value))}
            onBlur={() => setPhone((current) => formatBelarusPhone(current))}
            className="input"
          />
          {fieldErrors.phone ? (
            <span role="alert" className="text-sm text-rose-600">
              {fieldErrors.phone}
            </span>
          ) : null}
        </label>

        <label className="field mt-4">
          <span>
            Пароль <span className="text-rose-600">*</span>
          </span>
          <input
            type="password"
            value={password}
            aria-invalid={fieldErrors.password ? true : undefined}
            onChange={(event) => setPassword(event.target.value)}
            className="input"
          />
          {fieldErrors.password ? (
            <span role="alert" className="text-sm text-rose-600">
              {fieldErrors.password}
            </span>
          ) : null}
        </label>

        <label className="field mt-4">
          <span>
            Повторите пароль <span className="text-rose-600">*</span>
          </span>
          <input
            type="password"
            value={passwordConfirmation}
            aria-invalid={fieldErrors.passwordConfirmation ? true : undefined}
            onChange={(event) => setPasswordConfirmation(event.target.value)}
            className="input"
          />
          {fieldErrors.passwordConfirmation ? (
            <span role="alert" className="text-sm text-rose-600">
              {fieldErrors.passwordConfirmation}
            </span>
          ) : null}
        </label>

        {error ? (
          <p role="alert" className="alert alert-error mt-4">
            {error}
          </p>
        ) : null}

        <button type="submit" disabled={pending} className="btn btn-primary btn-block mt-6">
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

export default function RegisterPage() {
  return (
    <Suspense fallback={<main className="state-screen">Загрузка...</main>}>
      <RegisterForm />
    </Suspense>
  );
}
