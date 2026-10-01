"use client";

import { useEffect, useState } from "react";
import { apiFetch, readError } from "@/app/lib/api";
import type { DirectoryUser } from "@/app/lib/types";

const roles = ["admin", "analyst", "customer"];

export default function UsersPage() {
  const [users, setUsers] = useState<DirectoryUser[]>([]);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [role, setRole] = useState<string | null>(null);
  const readOnly = role === "analyst";

  useEffect(() => {
    const currentRole = localStorage.getItem("user_role");
    setRole(currentRole);

    apiFetch("/api/auth/admin/users")
      .then(async (response) => {
        if (!response.ok) {
          setError(await readError(response));
          return;
        }

        const body = (await response.json()) as { data: DirectoryUser[] };
        setUsers(body.data);
      })
      .catch(() => setError("Не удалось загрузить пользователей"));
  }, []);

  async function changeRole(user: DirectoryUser, nextRole: string) {
    setNotice("");
    setError("");

    const response = await apiFetch(`/api/auth/admin/users/${user.id}/role`, {
      method: "PATCH",
      body: JSON.stringify({ role: nextRole }),
    });

    if (!response.ok) {
      setError(await readError(response));
      return;
    }

    setUsers((current) =>
      current.map((item) => (item.id === user.id ? { ...item, role: nextRole } : item)),
    );
    setNotice(`Роль ${user.email} изменена на ${nextRole}`);
  }

  return (
    <section>
      <h1 className="text-2xl font-semibold">Пользователи и роли</h1>
      <p className="mt-1 text-sm text-slate-500">Данные из PostgreSQL сервиса аутентификации.</p>

      {readOnly ? (
        <p className="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
          Доступ только для чтения. Смена ролей доступна администратору.
        </p>
      ) : null}
      {notice ? <p className="mt-4 text-sm text-emerald-700">{notice}</p> : null}
      {error ? <p className="mt-4 text-sm text-red-600">{error}</p> : null}

      <div className="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white">
        <table className="w-full text-left text-sm">
          <thead className="bg-slate-50 text-slate-500">
            <tr>
              <th className="px-4 py-3 font-medium">ID</th>
              <th className="px-4 py-3 font-medium">Email</th>
              <th className="px-4 py-3 font-medium">Роль</th>
              <th className="px-4 py-3 font-medium">Регистрация</th>
            </tr>
          </thead>
          <tbody>
            {users.map((user) => (
              <tr key={user.id} className="border-t border-slate-100">
                <td className="px-4 py-3">{user.id}</td>
                <td className="px-4 py-3">{user.email}</td>
                <td className="px-4 py-3">
                  <select
                    value={user.role}
                    disabled={readOnly}
                    onChange={(event) => changeRole(user, event.target.value)}
                    className="rounded-lg border border-slate-300 px-2 py-1 disabled:bg-slate-100"
                  >
                    {roles.map((item) => (
                      <option key={item} value={item}>
                        {item}
                      </option>
                    ))}
                  </select>
                </td>
                <td className="px-4 py-3 text-slate-500">
                  {user.registered_at ? new Date(user.registered_at).toLocaleString("ru-RU") : "—"}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  );
}
