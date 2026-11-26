"use client";

import { useEffect, useState, type FormEvent } from "react";
import { useRouter } from "next/navigation";
import AppHeader from "@/app/components/AppHeader";
import {
  deleteAccountRequest,
  logoutAllSessions,
  requestEmailChange,
  updatePasswordRequest,
  updateProfileRequest,
} from "@/app/lib/api/auth";
import {
  applyPhoneChange,
  formatBelarusPhone,
  phoneFromProfile,
  phoneToSubmit,
} from "@/app/lib/phone";
import { resetCartOnLogout } from "@/app/lib/cart";
import type { AuthUser } from "@/app/lib/types";
import { SessionUnavailableScreen, useSession } from "@/app/providers/SessionProvider";

const fieldClass = "input";

function ProfileFormBody({
  user,
  onUserUpdated,
  onSessionCleared,
}: {
  user: AuthUser;
  onUserUpdated: () => Promise<AuthUser | null>;
  onSessionCleared: () => void;
}) {
  const router = useRouter();
  const initialPhone = phoneFromProfile(user.phone);
  const [name, setName] = useState(user.name);
  const [email, setEmail] = useState(user.email);
  const [savedEmail, setSavedEmail] = useState(user.email);
  const [emailPassword, setEmailPassword] = useState("");
  const [emailNotice, setEmailNotice] = useState("");
  const [phone, setPhone] = useState(initialPhone);
  const [storedPhone, setStoredPhone] = useState(initialPhone);
  const [phoneEdited, setPhoneEdited] = useState(false);
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

  async function saveProfile(event: FormEvent) {
    event.preventDefault();
    setProfileError("");
    setProfileMessage("");
    setProfilePending(true);

    try {
      const updated = await updateProfileRequest({
        name,
        email: savedEmail,
        phone: phoneToSubmit(storedPhone, phone, phoneEdited),
      });

      if (updated.user) {
        const nextStoredPhone = phoneFromProfile(updated.user.phone);
        setStoredPhone(nextStoredPhone);
        setPhoneEdited(false);
      }

      if (email !== savedEmail) {
        await requestEmailChange({ email, current_password: emailPassword });
        setEmailPassword("");
        setEmailNotice(
          "Ждём подтверждения нового адреса. До этого вход работает по старому email.",
        );
        setProfileMessage("Данные сохранены.");
        await onUserUpdated();
        return;
      }

      setSavedEmail(email);
      setProfileMessage("Профиль сохранён.");
      await onUserUpdated();
    } catch (reason: unknown) {
      setProfileError(reason instanceof Error ? reason.message : "Запрос не выполнен");
    } finally {
      setProfilePending(false);
    }
  }

  async function savePassword(event: FormEvent) {
    event.preventDefault();
    setPasswordError("");
    setPasswordMessage("");
    setPasswordPending(true);

    try {
      await updatePasswordRequest({
        current_password: currentPassword,
        password,
        password_confirmation: passwordConfirmation,
      });
      setCurrentPassword("");
      setPassword("");
      setPasswordConfirmation("");
      setPasswordMessage("Пароль обновлён.");
    } catch (reason: unknown) {
      setPasswordError(reason instanceof Error ? reason.message : "Запрос не выполнен");
    } finally {
      setPasswordPending(false);
    }
  }

  async function deleteAccount(event: FormEvent) {
    event.preventDefault();
    setDeleteError("");
    setDeletePending(true);

    try {
      await deleteAccountRequest({ password: deletePassword });
      resetCartOnLogout();
      onSessionCleared();
      router.replace("/login");
    } catch (reason: unknown) {
      setDeleteError(reason instanceof Error ? reason.message : "Запрос не выполнен");
      setDeletePending(false);
    }
  }

  async function handleLogoutAll() {
    await logoutAllSessions();
    onSessionCleared();
    router.replace("/login");
  }

  return (
    <div className="min-h-screen">
      <AppHeader />
      <main className="mx-auto max-w-3xl space-y-6 px-4 py-8 sm:px-6">
        <div>
          <h1 className="page-title">Профиль</h1>
          <p className="page-subtitle">Управление данными аккаунта и безопасностью.</p>
        </div>

        <form
          onSubmit={(event) => {
            void saveProfile(event);
          }}
          className="card p-6 sm:p-7"
        >
          <h2 className="text-lg font-semibold tracking-tight">Данные аккаунта</h2>
          <p className="mt-1 text-sm text-slate-500">Имя, телефон и email.</p>

          <label className="field mt-6">
            Имя
            <input
              required
              value={name}
              onChange={(event) => setName(event.target.value)}
              className={fieldClass}
            />
          </label>

          <label className="field mt-4">
            Телефон
            <input
              required
              type="tel"
              value={phone}
              placeholder="+375 (29) 123-45-67"
              onChange={(event) => {
                setPhoneEdited(true);
                setPhone(applyPhoneChange(phone, event.target.value));
              }}
              onBlur={() => {
                if (!phoneEdited) {
                  return;
                }
                setPhone((current) => formatBelarusPhone(current));
              }}
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
          {email !== savedEmail ? (
            <label className="field mt-4">
              Текущий пароль для смены email
              <input
                required
                type="password"
                value={emailPassword}
                onChange={(event) => setEmailPassword(event.target.value)}
                className={fieldClass}
              />
            </label>
          ) : null}
          {emailNotice ? <p className="alert alert-success mt-4">{emailNotice}</p> : null}

          {profileError ? (
            <p role="alert" className="alert alert-error mt-4">
              {profileError}
            </p>
          ) : null}
          {profileMessage ? <p className="alert alert-success mt-4">{profileMessage}</p> : null}

          <button type="submit" disabled={profilePending} className="btn btn-primary mt-6">
            {profilePending ? "Сохраняем..." : "Сохранить"}
          </button>
        </form>

        <form
          onSubmit={(event) => {
            void savePassword(event);
          }}
          className="card p-6 sm:p-7"
        >
          <h2 className="text-lg font-semibold tracking-tight">Пароль</h2>
          <p className="mt-1 text-sm text-slate-500">
            Используйте длинный пароль, который вы нигде больше не применяете.
          </p>

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

          {passwordError ? (
            <p role="alert" className="alert alert-error mt-4">
              {passwordError}
            </p>
          ) : null}
          {passwordMessage ? <p className="alert alert-success mt-4">{passwordMessage}</p> : null}

          <button type="submit" disabled={passwordPending} className="btn btn-primary mt-6">
            {passwordPending ? "Обновляем..." : "Обновить пароль"}
          </button>
        </form>

        <section className="card p-6 sm:p-7">
          <h2 className="text-lg font-semibold tracking-tight">Сессии</h2>
          <p className="mt-1 text-sm text-slate-500">
            Завершить вход на всех устройствах, включая это.
          </p>
          <button
            type="button"
            className="btn btn-secondary mt-6"
            onClick={() => {
              void handleLogoutAll();
            }}
          >
            Выйти на всех устройствах
          </button>
        </section>

        <form
          onSubmit={(event) => {
            void deleteAccount(event);
          }}
          className="card card-danger p-6 sm:p-7"
        >
          <h2 className="text-lg font-semibold text-rose-700">Удалить аккаунт</h2>
          <p className="mt-1 text-sm text-slate-500">
            После удаления войти с этим email будет нельзя.
          </p>

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

          {deleteError ? (
            <p role="alert" className="alert alert-error mt-4">
              {deleteError}
            </p>
          ) : null}

          <button type="submit" disabled={deletePending} className="btn btn-danger mt-6">
            {deletePending ? "Удаляем..." : "Удалить аккаунт"}
          </button>
        </form>
      </main>
    </div>
  );
}

export default function ProfilePage() {
  const router = useRouter();
  const { user, status, error, refresh, clear } = useSession();

  useEffect(() => {
    if (status === "guest") {
      router.replace("/login?next=%2Fprofile");
    }
  }, [router, status]);

  if (status === "unavailable") {
    return (
      <SessionUnavailableScreen
        message={error}
        onRetry={() => {
          void refresh();
        }}
      />
    );
  }

  if (status !== "authenticated" || !user) {
    return <main className="state-screen">Загружаем профиль...</main>;
  }

  return (
    <ProfileFormBody key={user.id} user={user} onUserUpdated={refresh} onSessionCleared={clear} />
  );
}
