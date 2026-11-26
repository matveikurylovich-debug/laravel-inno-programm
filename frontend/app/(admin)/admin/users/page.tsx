"use client";

import { useState } from "react";
import Pagination from "@/app/components/Pagination";
import { listAdminUsers, updateAdminUserRole } from "@/app/lib/api/auth";
import { canManageUsers, resolveUserRoles } from "@/app/lib/permissions";
import type { DirectoryUser } from "@/app/lib/types";
import { useAsyncQuery } from "@/app/lib/use-async-query";
import { useSession } from "@/app/providers/SessionProvider";

const DEFAULT_ROLES = ["admin", "analyst", "customer"];

export default function UsersPage() {
  const { user: sessionUser } = useSession();
  const [page, setPage] = useState(1);
  const [actionError, setActionError] = useState("");
  const [notice, setNotice] = useState("");
  const [updatingUserId, setUpdatingUserId] = useState<number | null>(null);

  const usersQuery = useAsyncQuery(
    (signal) => listAdminUsers({ page }, signal),
    `admin:users:${page}`,
    { fallbackError: "Не удалось загрузить пользователей" },
  );

  const users = usersQuery.data?.data ?? [];
  const availableRoles = usersQuery.data?.roles ?? DEFAULT_ROLES;
  const meta = usersQuery.data?.meta;
  const readOnly = !canManageUsers(sessionUser);
  const error = actionError || usersQuery.error;

  async function changeRole(targetUser: DirectoryUser, nextRole: string) {
    if (nextRole === targetUser.role) {
      return;
    }

    const currentRoles = resolveUserRoles(targetUser);
    if (
      nextRole === "admin" &&
      !currentRoles.includes("admin") &&
      !window.confirm(
        `Назначить пользователю ${targetUser.email} роль «admin» с полными правами администратора?`,
      )
    ) {
      return;
    }

    setNotice("");
    setActionError("");
    setUpdatingUserId(targetUser.id);

    try {
      const response = await updateAdminUserRole(targetUser.id, nextRole);
      const updatedUser = response.user;
      usersQuery.mutate((current) =>
        current
          ? {
              ...current,
              data: current.data.map((item) =>
                item.id === targetUser.id
                  ? {
                      ...item,
                      ...(updatedUser ?? {}),
                      role: updatedUser?.role ?? nextRole,
                      roles: updatedUser?.roles ?? [nextRole],
                    }
                  : item,
              ),
            }
          : current,
      );
      setNotice(`Роль ${targetUser.email} изменена на ${nextRole}`);
    } catch (reason: unknown) {
      setActionError(reason instanceof Error ? reason.message : "Не удалось изменить роль");
    } finally {
      setUpdatingUserId(null);
    }
  }

  return (
    <section>
      <h1 className="page-title">Пользователи и роли</h1>
      <p className="page-subtitle">Управление учётными записями и правами доступа.</p>

      {readOnly ? (
        <p className="alert alert-warning mt-4">
          Доступ только для чтения. Смена ролей доступна администратору.
        </p>
      ) : null}
      {notice ? <p className="alert alert-success mt-4">{notice}</p> : null}
      {error ? (
        <p role="alert" className="alert alert-error mt-4">
          {error}
        </p>
      ) : null}

      <div className="table-wrap mt-6">
        <table className="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Email</th>
              <th>Все роли</th>
              <th>Основная роль</th>
              <th>Регистрация</th>
            </tr>
          </thead>
          <tbody>
            {usersQuery.isLoading ? (
              <tr>
                <td colSpan={5} className="!py-12 text-center text-slate-500">
                  Загрузка пользователей...
                </td>
              </tr>
            ) : users.length === 0 ? (
              <tr>
                <td colSpan={5} className="!py-12 text-center text-slate-500">
                  Пользователей пока нет
                </td>
              </tr>
            ) : (
              users.map((item) => {
                const assignedRoles =
                  item.roles && item.roles.length > 0 ? item.roles : [item.role];
                const editable = canManageUsers(sessionUser, item);
                return (
                  <tr key={item.id}>
                    <td className="text-slate-500">{item.id}</td>
                    <td className="font-medium">{item.email}</td>
                    <td>
                      <div className="flex flex-wrap gap-1.5">
                        {assignedRoles.map((roleName) => (
                          <span
                            key={roleName}
                            className={roleName === "admin" ? "badge badge-brand" : "badge"}
                          >
                            {roleName}
                          </span>
                        ))}
                      </div>
                    </td>
                    <td>
                      <select
                        aria-label={`Роль пользователя ${item.email}`}
                        value={item.role}
                        disabled={!editable || updatingUserId === item.id}
                        onChange={(event) => {
                          void changeRole(item, event.target.value);
                        }}
                        className="select select-sm w-auto"
                      >
                        {availableRoles.map((roleOption) => (
                          <option key={roleOption} value={roleOption}>
                            {roleOption}
                          </option>
                        ))}
                      </select>
                    </td>
                    <td className="text-slate-500">
                      {item.registered_at
                        ? new Date(item.registered_at).toLocaleString("ru-RU")
                        : "—"}
                    </td>
                  </tr>
                );
              })
            )}
          </tbody>
        </table>
      </div>

      <Pagination
        currentPage={meta?.current_page ?? page}
        lastPage={meta?.last_page ?? 1}
        onPageChange={setPage}
      />
    </section>
  );
}
