"use client";

import { Suspense, useState, useSyncExternalStore, type FormEvent } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import Brand from "@/app/components/Brand";
import { resendTwoFactorRequest, verifyTwoFactorRequest } from "@/app/lib/api/auth";
import { destinationFor, safeNextPath } from "@/app/lib/auth";
import { setCartUser } from "@/app/lib/cart";
import {
  currentChallengeId,
  rememberChallengeId,
  subscribeChallengeId,
} from "@/app/lib/two-factor-challenge";
import { useSession } from "@/app/providers/SessionProvider";

function TwoFactorForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const { refresh } = useSession();
  const challengeId = useSyncExternalStore(subscribeChallengeId, currentChallengeId, () => "");
  const clientReady = useSyncExternalStore(
    subscribeChallengeId,
    () => true,
    () => false,
  );
  const [code, setCode] = useState("");
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");
  const [pending, setPending] = useState(false);

  async function onSubmit(event: FormEvent) {
    event.preventDefault();
    setError("");
    setPending(true);

    try {
      const data = await verifyTwoFactorRequest({ challenge_id: challengeId, code });
      setCartUser(data.user.id);
      await refresh();
      router.replace(safeNextPath(searchParams.get("next"), destinationFor(data.user)));
    } catch (reason: unknown) {
      setError(reason instanceof Error ? reason.message : "Запрос не выполнен");
      setPending(false);
    }
  }

  async function resend() {
    setError("");
    setMessage("");

    try {
      const payload = await resendTwoFactorRequest({ challenge_id: challengeId });
      rememberChallengeId(payload.challenge_id);
      setMessage("Новый код отправлен на почту.");
    } catch (reason: unknown) {
      setError(reason instanceof Error ? reason.message : "Запрос не выполнен");
    }
  }

  if (!clientReady) {
    return <main className="state-screen">Загрузка...</main>;
  }

  if (!challengeId) {
    return (
      <main className="auth-shell">
        <p className="auth-card text-center text-sm text-slate-600">
          Сначала войдите по паролю.{" "}
          <Link href="/login" className="link">
            Ко входу
          </Link>
        </p>
      </main>
    );
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
        <h1 className="auth-title">Код из письма</h1>
        <p className="auth-hint">
          Код отправлен. Если письмо не пришло, запросите ещё раз. Код действует 5 минут.
        </p>

        <label className="field mt-6">
          Код
          <input
            required
            inputMode="numeric"
            pattern="\d{6}"
            maxLength={6}
            value={code}
            onChange={(event) => setCode(event.target.value.replace(/\D/g, "").slice(0, 6))}
            className="input text-center font-mono text-2xl tracking-[0.5em]"
          />
        </label>

        {error ? (
          <p role="alert" className="alert alert-error mt-4">
            {error}
          </p>
        ) : null}
        {message ? <p className="alert alert-success mt-4">{message}</p> : null}

        <button type="submit" disabled={pending} className="btn btn-primary btn-block mt-6">
          {pending ? "Проверяем..." : "Подтвердить"}
        </button>

        <button
          type="button"
          onClick={() => {
            void resend();
          }}
          className="btn btn-ghost btn-block mt-3"
        >
          Отправить код ещё раз
        </button>
      </form>
    </main>
  );
}

export default function TwoFactorPage() {
  return (
    <Suspense fallback={<main className="state-screen">Загрузка...</main>}>
      <TwoFactorForm />
    </Suspense>
  );
}
