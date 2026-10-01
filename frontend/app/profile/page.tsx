"use client";

import { FormEvent, useEffect, useState } from "react";
import AppHeader from "@/app/components/AppHeader";
import { apiFetch, clearSession, readError } from "@/app/lib/api";
import { formatBelarusPhone } from "@/app/lib/phone";
import type { AuthUser } from "@/app/lib/types";

const fieldClass = "mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900";

export default function ProfilePage() {
  const [ready, setReady] = useState(false);
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [phone, setPhone] = useState("");
  const [profileError, setProfileError] = useState("");
  const [profileMessage, setProfileMessage] = useState("");
  const [profilePending, setProfilePending] = useState(false);

  const [currentPassword, setCurrentPassword] = useState("");
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [passwordError, setPasswordError] = useState("");
  const [passwordMessage, setPasswordMessage] = useState("");
  const [passwordPending, setPasswordPending] = useState(false);

  const [deletePassword, setDeletePassword] = useState("");
  const [deleteError, setDeleteError] = useState("");
  const [deletePending, setDeletePending] = useState(false);

  useEffect(() => {
    const token = localStorage.getItem("access_token");
    if (!token) {
      window.location.href = "/login";
      return;
    }

    apiFetch("/api/auth/me")
      .then(async (response) => {
        if (!response.ok) {
          return;
        }

        const body = (await response.json()) as { user: AuthUser };
        setName(body.user.name);
        setEmail(body.user.email);
        setPhone(body.user.phone ? formatBelarusPhone(body.user.phone) : "");
        setReady(true);
      })
      .catch(() => undefined);
  }, []);

  async function saveProfile(event: FormEvent) {
    event.preventDefault();
    setProfileError("");
    setProfileMessage("");
    setProfilePending(true);

    const response = await apiFetch("/api/auth/profile", {
      method: "PATCH",
      body: JSON.stringify({ name, email, phone }),
    });

    setProfilePending(false);

    if (!response.ok) {
      setProfileError(await readError(response));
      return;
    }

    const body = (await response.json()) as { user: AuthUser };
    localStorage.setItem("user_name", body.user.name);
    setProfileMessage("Сохранено.");
  }

  async function savePassword(event: FormEvent) {
    event.preventDefault();
    setPasswordError("");
    setPasswordMessage("");
    setPasswordPending(true);

    const response = await apiFetch("/api/auth/profile/password", {
      method: "PUT",
      body: JSON.stringify({
        current_password: currentPassword,
        password,
        password_confirmation: passwordConfirmation,
      }),
    });

    setPasswordPending(false);

    if (!response.ok) {
      setPasswordError(await readError(response));
      return;
    }

    setCurrentPassword("");
    setPassword("");
    setPasswordConfirmation("");
    setPasswordMessage("Пароль обновлён.");
  }

  async function deleteAccount(event: FormEvent) {
    event.preventDefault();
    setDeleteError("");
    setDeletePending(true);

    const response = await apiFetch("/api/auth/profile", {
      method: "DELETE",
      body: JSON.stringify({ password: deletePassword }),
    });

    if (!response.ok) {
      setDeleteError(await readError(response));
      setDeletePending(false);
      return;
    }

    clearSession();
    window.location.href = "/login";
  }

  if (!ready) {
    return <main className="p-8 text-sm text-slate-500">Загружаем профиль...</main>;
  }

  return (
    <div className="min-h-screen">
      <AppHeader />
      <main className="mx-auto max-w-3xl space-y-6 px-4 py-8 sm:px-6">
        <h1 className="text-2xl font-semibold">Профиль</h1>

        <form onSubmit={saveProfile} className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <h2 className="text-lg font-medium">Данные аккаунта</h2>
          <p className="mt-1 text-sm text-slate-500">Имя, телефон и email.</p>

          <label className="mt-6 block text-sm font-medium">
            Имя
            <input required value={name} onChange={(event) => setName(event.target.value)} className={fieldClass} />
          </label>

          <label className="mt-4 block text-sm font-medium">
            Телефон
            <input
              required
              type="tel"
              value={phone}
              placeholder="+375 (29) 123-45-67"
              onChange={(event) => setPhone(formatBelarusPhone(event.target.value))}
              className={fieldClass}
            />
          </label>

          <label className="mt-4 block text-sm font-medium">
            Email
            <input
              required
              type="email"
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              className={fieldClass}
            />
          </label>

          {profileError ? <p className="mt-4 text-sm text-red-600">{profileError}</p> : null}
          {profileMessage ? <p className="mt-4 text-sm text-emerald-700">{profileMessage}</p> : null}

          <button
            type="submit"
            disabled={profilePending}
            className="mt-6 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white disabled:opacity-60"
          >
            {profilePending ? "Сохраняем..." : "Сохранить"}
          </button>
        </form>

        <form onSubmit={savePassword} className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <h2 className="text-lg font-medium">Пароль</h2>
          <p className="mt-1 text-sm text-slate-500">Используйте длинный пароль, который вы нигде больше не применяете.</p>

          <label className="mt-6 block text-sm font-medium">
            Текущий пароль
            <input
              required
              type="password"
              value={currentPassword}
              onChange={(event) => setCurrentPassword(event.target.value)}
              className={fieldClass}
            />
          </label>

          <label className="mt-4 block text-sm font-medium">
            Новый пароль
            <input
              required
              type="password"
              minLength={8}
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              className={fieldClass}
            />
          </label>

          <label className="mt-4 block text-sm font-medium">
            Повторите новый пароль
            <input
              required
              type="password"
              value={passwordConfirmation}
              onChange={(event) => setPasswordConfirmation(event.target.value)}
              className={fieldClass}
            />
          </label>

          {passwordError ? <p className="mt-4 text-sm text-red-600">{passwordError}</p> : null}
          {passwordMessage ? <p className="mt-4 text-sm text-emerald-700">{passwordMessage}</p> : null}

          <button
            type="submit"
            disabled={passwordPending}
            className="mt-6 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white disabled:opacity-60"
          >
            {passwordPending ? "Обновляем..." : "Обновить пароль"}
          </button>
        </form>

        <form onSubmit={deleteAccount} className="rounded-2xl border border-red-200 bg-white p-6 shadow-sm">
          <h2 className="text-lg font-medium text-red-700">Удалить аккаунт</h2>
          <p className="mt-1 text-sm text-slate-500">После удаления войти с этим email будет нельзя.</p>

          <label className="mt-6 block text-sm font-medium">
            Пароль
            <input
              required
              type="password"
              value={deletePassword}
              onChange={(event) => setDeletePassword(event.target.value)}
              className={fieldClass}
            />
          </label>

          {deleteError ? <p className="mt-4 text-sm text-red-600">{deleteError}</p> : null}

          <button
            type="submit"
            disabled={deletePending}
            className="mt-6 rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white disabled:opacity-60"
          >
            {deletePending ? "Удаляем..." : "Удалить аккаунт"}
          </button>
        </form>
      </main>
    </div>
  );
}
