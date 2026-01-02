import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Layout from '../../components/Layout';
import {
    PlusIcon,
    PencilIcon,
    MagnifyingGlassIcon,
    XMarkIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
} from '@heroicons/react/24/outline';

export default function Index({ users, roles, filters }) {
    const [search, setSearch] = useState(filters.search || '');

    const handleFilter = (key, value) => {
        router.get('/users', {
            ...filters,
            [key]: value,
            page: 1, // Reset to first page on filter change
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleSearch = (e) => {
        e.preventDefault();
        handleFilter('search', search);
    };

    const clearFilters = () => {
        router.get('/users', {}, {
            preserveState: true,
            preserveScroll: true,
        });
        setSearch('');
    };

    const hasActiveFilters = Boolean(filters.search) || (filters.active !== '' && filters.active !== null && filters.active !== undefined) || Boolean(filters.auth_provider) || Boolean(filters.role_id);

    return (
        <Layout>
            <Head title="Users" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Users</h1>
                        <p className="mt-1 text-sm text-gray-500">
                            Manage user accounts and permissions
                        </p>
                    </div>
                    <Link href="/users/create" className="btn-primary flex items-center">
                        <PlusIcon className="w-5 h-5 mr-2" />
                        Add User
                    </Link>
                </div>

                {/* Filters */}
                <div className="card">
                    <div className="card-body">
                        <div className="flex flex-wrap gap-4 items-end">
                            {/* Search */}
                            <div className="flex-1 min-w-64">
                                <label htmlFor="search" className="label">
                                    Search
                                </label>
                                <form onSubmit={handleSearch} className="flex gap-2">
                                    <div className="relative flex-1">
                                        <input
                                            type="text"
                                            id="search"
                                            className="input pl-10"
                                            placeholder="Search by name or email..."
                                            value={search}
                                            onChange={(e) => setSearch(e.target.value)}
                                        />
                                        <MagnifyingGlassIcon className="w-5 h-5 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
                                    </div>
                                    <button type="submit" className="btn-secondary">
                                        Search
                                    </button>
                                </form>
                            </div>

                            {/* Status Filter */}
                            <div>
                                <label htmlFor="active" className="label">
                                    Status
                                </label>
                                <select
                                    id="active"
                                    className="input"
                                    value={filters.active}
                                    onChange={(e) => handleFilter('active', e.target.value)}
                                >
                                    <option value="">All Statuses</option>
                                    <option value="true">Active</option>
                                    <option value="false">Inactive</option>
                                </select>
                            </div>

                            {/* Auth Provider Filter */}
                            <div>
                                <label htmlFor="auth_provider" className="label">
                                    Auth Method
                                </label>
                                <select
                                    id="auth_provider"
                                    className="input"
                                    value={filters.auth_provider}
                                    onChange={(e) => handleFilter('auth_provider', e.target.value)}
                                >
                                    <option value="">All Methods</option>
                                    <option value="password">Password</option>
                                    <option value="google">Google SSO</option>
                                </select>
                            </div>

                            {/* Role Filter */}
                            <div>
                                <label htmlFor="role_id" className="label">
                                    Role
                                </label>
                                <select
                                    id="role_id"
                                    className="input"
                                    value={filters.role_id}
                                    onChange={(e) => handleFilter('role_id', e.target.value)}
                                >
                                    <option value="">All Roles</option>
                                    {roles.map((role) => (
                                        <option key={role.id} value={role.id}>
                                            {role.name.charAt(0).toUpperCase() + role.name.slice(1)}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* Clear Filters */}
                            {hasActiveFilters && (
                                <button
                                    type="button"
                                    onClick={clearFilters}
                                    className="btn-secondary flex items-center"
                                >
                                    <XMarkIcon className="w-4 h-4 mr-1" />
                                    Clear
                                </button>
                            )}
                        </div>
                    </div>
                </div>

                {/* Users Table */}
                <div className="card">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        User
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Role
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Auth Method
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {users.data.length === 0 ? (
                                    <tr>
                                        <td colSpan="5" className="px-6 py-12 text-center text-gray-500">
                                            No users found
                                        </td>
                                    </tr>
                                ) : (
                                    users.data.map((user) => (
                                        <tr key={user.id} className="hover:bg-gray-50">
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <div className="flex items-center">
                                                    <div className="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                                        <span className="text-sm font-medium text-blue-700">
                                                            {user.name?.charAt(0)?.toUpperCase() || 'U'}
                                                        </span>
                                                    </div>
                                                    <div className="ml-4">
                                                        <div className="text-sm font-medium text-gray-900">
                                                            {user.name}
                                                        </div>
                                                        <div className="text-sm text-gray-500">
                                                            {user.email}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                                    user.role?.name === 'admin'
                                                        ? 'bg-purple-100 text-purple-800'
                                                        : 'bg-gray-100 text-gray-800'
                                                }`}>
                                                    {user.role?.name ? user.role.name.charAt(0).toUpperCase() + user.role.name.slice(1) : 'No Role'}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                                    user.auth_provider === 'google'
                                                        ? 'bg-blue-100 text-blue-800'
                                                        : 'bg-gray-100 text-gray-800'
                                                }`}>
                                                    {user.auth_provider === 'google' ? 'Google SSO' : 'Password'}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                                    user.is_active
                                                        ? 'bg-green-100 text-green-800'
                                                        : 'bg-red-100 text-red-800'
                                                }`}>
                                                    {user.is_active ? 'Active' : 'Inactive'}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <Link
                                                    href={`/users/${user.id}/edit`}
                                                    className="text-blue-600 hover:text-blue-900 inline-flex items-center"
                                                >
                                                    <PencilIcon className="w-4 h-4 mr-1" />
                                                    Edit
                                                </Link>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {users.last_page > 1 && (
                        <div className="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
                            <div className="text-sm text-gray-500">
                                Showing {users.from} to {users.to} of {users.total} users
                            </div>
                            <div className="flex gap-2">
                                {users.prev_page_url && (
                                    <Link
                                        href={users.prev_page_url}
                                        className="btn-secondary flex items-center"
                                        preserveScroll
                                    >
                                        <ChevronLeftIcon className="w-4 h-4 mr-1" />
                                        Previous
                                    </Link>
                                )}
                                {users.next_page_url && (
                                    <Link
                                        href={users.next_page_url}
                                        className="btn-secondary flex items-center"
                                        preserveScroll
                                    >
                                        Next
                                        <ChevronRightIcon className="w-4 h-4 ml-1" />
                                    </Link>
                                )}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </Layout>
    );
}
