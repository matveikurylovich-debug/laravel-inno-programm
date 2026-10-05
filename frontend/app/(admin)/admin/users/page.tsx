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
      <h1 className="page-title">Пользователи и роли</h1>
      <p className="page-subtitle">Данные из PostgreSQL сервиса аутентификации.</p>

      {readOnly ? (
        <p className="alert alert-warning mt-4">
          Доступ только для чтения. Смена ролей доступна администратору.
        </p>
      ) : null}
      {notice ? <p className="alert alert-success mt-4">{notice}</p> : null}
      {error ? <p className="alert alert-error mt-4">{error}</p> : null}

      <div className="table-wrap mt-6">
        <table className="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Email</th>
              <th>Роль</th>
              <th>Регистрация</th>
            </tr>
          </thead>
          <tbody>
            {users.map((user) => (
              <tr key={user.id}>
                <td className="text-slate-500">{user.id}</td>
                <td className="font-medium">{user.email}</td>
                <td>
                  <select
                    value={user.role}
                    disabled={readOnly}
                    onChange={(event) => changeRole(user, event.target.value)}
                    className="select select-sm w-auto"
                  >
                    {roles.map((item) => (
                      <option key={item} value={item}>
                        {item}
                      </option>
                    ))}
                  </select>
                </td>
                <td className="text-slate-500">
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
