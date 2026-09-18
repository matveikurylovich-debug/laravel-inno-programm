import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';

export default function Dashboard({ users }) {
    const { auth, flash } = usePage().props;

    const handleUpdateRole = (userId, role) => {
        router.patch(`/admin/users/${userId}/role`, { role });
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Панель администратора
                </h2>
            }
        >
            <Head title="Панель администратора" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    {flash?.message && (
                        <div className="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
                            {flash.message}
                        </div>
                    )}

                    {flash?.error && (
                        <div className="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                            {flash.error}
                        </div>
                    )}

                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <p className="mb-4 text-lg font-medium">
                                Добро пожаловать в панель администратора!
                            </p>

                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th
                                                scope="col"
                                                className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                                            >
                                                Имя
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                                            >
                                                Email
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                                            >
                                                Роли
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                                            >
                                                Дата регистрации
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200 bg-white">
                                        {users.map((user) => (
                                            <tr key={user.id}>
                                                <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-900">
                                                    {user.name}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                                                    {user.email}
                                                </td>
                                                <td className="px-4 py-3 text-sm text-gray-600">
                                                    <select
                                                        value={user.roles?.[0]?.name || 'customer'}
                                                        disabled={user.id === auth.user.id}
                                                        onChange={(e) =>
                                                            handleUpdateRole(
                                                                user.id,
                                                                e.target.value,
                                                            )
                                                        }
                                                        className="rounded-md border-gray-300 py-1 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:opacity-60"
                                                    >
                                                        <option value="customer">customer</option>
                                                        <option value="admin">admin</option>
                                                        <option value="analyst">analyst</option>
                                                    </select>
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                                                    {new Date(
                                                        user.created_at,
                                                    ).toLocaleDateString('ru-RU')}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
