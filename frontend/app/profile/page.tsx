"use client";

import { FormEvent, useEffect, useState } from "react";
import AppHeader from "@/app/components/AppHeader";
import { apiFetch, clearSession, readError } from "@/app/lib/api";
import { formatBelarusPhone } from "@/app/lib/phone";
import type { AuthUser } from "@/app/lib/types";

const fieldClass = "input";

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
    return <main className="state-screen">Загружаем профиль...</main>;
  }

  return (
    <div className="min-h-screen">
      <AppHeader />
      <main className="mx-auto max-w-3xl space-y-6 px-4 py-8 sm:px-6">
        <div>
          <h1 className="page-title">Профиль</h1>
          <p className="page-subtitle">Управление данными аккаунта и безопасностью.</p>
        </div>

        <form onSubmit={saveProfile} className="card p-6 sm:p-7">
          <h2 className="text-lg font-semibold tracking-tight">Данные аккаунта</h2>
          <p className="mt-1 text-sm text-slate-500">Имя, телефон и email.</p>

          <label className="field mt-6">
            Имя
            <input required value={name} onChange={(event) => setName(event.target.value)} className={fieldClass} />
          </label>

          <label className="field mt-4">
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

          <label className="field mt-4">
            Email
            <input
              required
              type="email"
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              className={fieldClass}
            />
          </label>

          {profileError ? <p className="alert alert-error mt-4">{profileError}</p> : null}
          {profileMessage ? <p className="alert alert-success mt-4">{profileMessage}</p> : null}

          <button
            type="submit"
            disabled={profilePending}
            className="btn btn-primary mt-6"
          >
            {profilePending ? "Сохраняем..." : "Сохранить"}
          </button>
        </form>

        <form onSubmit={savePassword} className="card p-6 sm:p-7">
          <h2 className="text-lg font-semibold tracking-tight">Пароль</h2>
          <p className="mt-1 text-sm text-slate-500">Используйте длинный пароль, который вы нигде больше не применяете.</p>

          <label className="field mt-6">
            Текущий пароль
            <input
              required
              type="password"
              value={currentPassword}
              onChange={(event) => setCurrentPassword(event.target.value)}
              className={fieldClass}
            />
          </label>

          <label className="field mt-4">
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

          <label className="field mt-4">
            Повторите новый пароль
            <input
              required
              type="password"
              value={passwordConfirmation}
              onChange={(event) => setPasswordConfirmation(event.target.value)}
              className={fieldClass}
            />
          </label>

          {passwordError ? <p className="alert alert-error mt-4">{passwordError}</p> : null}
          {passwordMessage ? <p className="alert alert-success mt-4">{passwordMessage}</p> : null}

          <button
            type="submit"
            disabled={passwordPending}
            className="btn btn-primary mt-6"
          >
            {passwordPending ? "Обновляем..." : "Обновить пароль"}
          </button>
        </form>

        <form onSubmit={deleteAccount} className="card card-danger p-6 sm:p-7">
          <h2 className="text-lg font-semibold text-rose-700">Удалить аккаунт</h2>
          <p className="mt-1 text-sm text-slate-500">После удаления войти с этим email будет нельзя.</p>

          <label className="field mt-6">
            Пароль
            <input
              required
              type="password"
              value={deletePassword}
              onChange={(event) => setDeletePassword(event.target.value)}
              className={fieldClass}
            />
          </label>

          {deleteError ? <p className="alert alert-error mt-4">{deleteError}</p> : null}

          <button
            type="submit"
            disabled={deletePending}
            className="btn btn-danger mt-6"
          >
            {deletePending ? "Удаляем..." : "Удалить аккаунт"}
          </button>
        </form>
      </main>
    </div>
  );
}
