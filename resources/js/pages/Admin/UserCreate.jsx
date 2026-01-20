import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from './Index';
import { ArrowLeftIcon, EyeIcon, EyeSlashIcon } from '@heroicons/react/24/outline';

export default function UserCreate({ roles }) {
    const [showPassword, setShowPassword] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        role_id: '',
        auth_provider: 'password',
        password: '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('admin.users.store'));
    };

    return (
        <AdminLayout currentTab="users">
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Link
                        href={route('admin.users.index')}
                        className="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100"
                    >
                        <ArrowLeftIcon className="w-5 h-5" />
                    </Link>
                    <div>
                        <h2 className="text-lg font-medium text-gray-900">Create User</h2>
                        <p className="mt-1 text-sm text-gray-500">
                            Add a new user to the system
                        </p>
                    </div>
                </div>

                {/* Form */}
                <div className="card max-w-2xl">
                    <div className="card-body">
                        <form onSubmit={handleSubmit} className="space-y-6">
                            {/* Name */}
                            <div>
                                <label htmlFor="name" className="label">
                                    Full Name <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="name"
                                    className="input"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="Enter full name"
                                    required
                                />
                                {errors.name && (
                                    <p className="mt-1 text-sm text-red-600">{errors.name}</p>
                                )}
                            </div>

                            {/* Email */}
                            <div>
                                <label htmlFor="email" className="label">
                                    Email Address <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="email"
                                    id="email"
                                    className="input"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    placeholder="user@example.com"
                                    required
                                />
                                {errors.email && (
                                    <p className="mt-1 text-sm text-red-600">{errors.email}</p>
                                )}
                            </div>

                            {/* Role */}
                            <div>
                                <label htmlFor="role_id" className="label">
                                    Role <span className="text-red-500">*</span>
                                </label>
                                <select
                                    id="role_id"
                                    className="input"
                                    value={data.role_id}
                                    onChange={(e) => setData('role_id', e.target.value)}
                                    required
                                >
                                    <option value="">Select a role</option>
                                    {roles.map((role) => (
                                        <option key={role.id} value={role.id}>
                                            {role.name.charAt(0).toUpperCase() + role.name.slice(1)}
                                            {role.description && ` - ${role.description}`}
                                        </option>
                                    ))}
                                </select>
                                {errors.role_id && (
                                    <p className="mt-1 text-sm text-red-600">{errors.role_id}</p>
                                )}
                            </div>

                            {/* Auth Provider */}
                            <div>
                                <label className="label">
                                    Authentication Method <span className="text-red-500">*</span>
                                </label>
                                <div className="mt-2 space-y-3">
                                    <label className="flex items-start p-4 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50">
                                        <input
                                            type="radio"
                                            name="auth_provider"
                                            value="password"
                                            checked={data.auth_provider === 'password'}
                                            onChange={(e) => setData('auth_provider', e.target.value)}
                                            className="mt-0.5 h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500"
                                        />
                                        <div className="ml-3">
                                            <span className="block text-sm font-medium text-gray-900">
                                                Password
                                            </span>
                                            <span className="block text-sm text-gray-500">
                                                User will log in with email and password
                                            </span>
                                        </div>
                                    </label>
                                    <label className="flex items-start p-4 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50">
                                        <input
                                            type="radio"
                                            name="auth_provider"
                                            value="google"
                                            checked={data.auth_provider === 'google'}
                                            onChange={(e) => setData('auth_provider', e.target.value)}
                                            className="mt-0.5 h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500"
                                        />
                                        <div className="ml-3">
                                            <span className="block text-sm font-medium text-gray-900">
                                                Google SSO
                                            </span>
                                            <span className="block text-sm text-gray-500">
                                                User will log in with their Google account
                                            </span>
                                        </div>
                                    </label>
                                </div>
                                {errors.auth_provider && (
                                    <p className="mt-1 text-sm text-red-600">{errors.auth_provider}</p>
                                )}
                            </div>

                            {/* Password (only for password auth) */}
                            {data.auth_provider === 'password' && (
                                <div>
                                    <label htmlFor="password" className="label">
                                        Password <span className="text-red-500">*</span>
                                    </label>
                                    <div className="relative">
                                        <input
                                            type={showPassword ? 'text' : 'password'}
                                            id="password"
                                            className="input pr-10"
                                            value={data.password}
                                            onChange={(e) => setData('password', e.target.value)}
                                            placeholder="Enter a secure password"
                                            required={data.auth_provider === 'password'}
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowPassword(!showPassword)}
                                            className="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-gray-600"
                                            aria-label={showPassword ? 'Hide password' : 'Show password'}
                                        >
                                            {showPassword ? (
                                                <EyeSlashIcon className="w-5 h-5" />
                                            ) : (
                                                <EyeIcon className="w-5 h-5" />
                                            )}
                                        </button>
                                    </div>
                                    <p className="mt-1 text-xs text-gray-500">
                                        Minimum 8 characters, with mixed case and numbers
                                    </p>
                                    {errors.password && (
                                        <p className="mt-1 text-sm text-red-600">{errors.password}</p>
                                    )}
                                </div>
                            )}

                            {/* Actions */}
                            <div className="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-3 pt-4 border-t border-gray-200">
                                <Link href={route('admin.users.index')} className="btn-secondary w-full sm:w-auto text-center">
                                    Cancel
                                </Link>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="btn-primary w-full sm:w-auto"
                                >
                                    {processing ? 'Creating...' : 'Create User'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
