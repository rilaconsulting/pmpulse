import { Link, useForm, router } from '@inertiajs/react';
import { useState, useEffect, useCallback } from 'react';
import AdminLayout from './Index';
import { ArrowLeftIcon, EyeIcon, EyeSlashIcon, ExclamationTriangleIcon } from '@heroicons/react/24/outline';

export default function UserEdit({ user, roles, canDeactivate }) {
    const [showPassword, setShowPassword] = useState(false);
    const [showDeactivateModal, setShowDeactivateModal] = useState(false);

    // Handle Escape key to close modal
    const handleKeyDown = useCallback((e) => {
        if (e.key === 'Escape' && showDeactivateModal) {
            setShowDeactivateModal(false);
        }
    }, [showDeactivateModal]);

    useEffect(() => {
        document.addEventListener('keydown', handleKeyDown);
        return () => document.removeEventListener('keydown', handleKeyDown);
    }, [handleKeyDown]);

    const { data, setData, patch, processing, errors } = useForm({
        name: user.name || '',
        email: user.email || '',
        role_id: user.role_id || '',
        is_active: user.is_active,
        password: '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        patch(route('admin.users.update', user.id));
    };

    const handleDeactivate = () => {
        router.delete(route('admin.users.destroy', user.id), {
            onSuccess: () => setShowDeactivateModal(false),
        });
    };

    return (
        <AdminLayout currentTab="users">
            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div className="flex items-center gap-4">
                        <Link
                            href={route('admin.users.index')}
                            className="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100"
                        >
                            <ArrowLeftIcon className="w-5 h-5" />
                        </Link>
                        <div>
                            <h2 className="text-lg font-medium text-gray-900">Edit User</h2>
                            <p className="mt-1 text-sm text-gray-500">
                                Update user information and permissions
                            </p>
                        </div>
                    </div>

                    {/* Status Badge */}
                    <span className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium self-start sm:self-auto ${
                        user.is_active
                            ? 'bg-green-100 text-green-800'
                            : 'bg-red-100 text-red-800'
                    }`}>
                        {user.is_active ? 'Active' : 'Inactive'}
                    </span>
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

                            {/* Auth Provider (read-only) */}
                            <div>
                                <label className="label">Authentication Method</label>
                                <div className="mt-1 px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg">
                                    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                        user.auth_provider === 'google'
                                            ? 'bg-blue-100 text-blue-800'
                                            : 'bg-gray-100 text-gray-800'
                                    }`}>
                                        {user.auth_provider === 'google' ? 'Google SSO' : 'Password'}
                                    </span>
                                    <p className="mt-2 text-xs text-gray-500">
                                        Authentication method cannot be changed after account creation.
                                    </p>
                                </div>
                            </div>

                            {/* Password (only for password auth) */}
                            {user.auth_provider === 'password' && (
                                <div>
                                    <label htmlFor="password" className="label">
                                        New Password
                                        <span className="ml-2 text-xs text-gray-400 font-normal">
                                            (leave empty to keep current)
                                        </span>
                                    </label>
                                    <div className="relative">
                                        <input
                                            type={showPassword ? 'text' : 'password'}
                                            id="password"
                                            className="input pr-10"
                                            value={data.password}
                                            onChange={(e) => setData('password', e.target.value)}
                                            placeholder="Enter a new password"
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

                            {/* Status Toggle */}
                            <div className="p-4 bg-gray-50 border border-gray-200 rounded-lg">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <label htmlFor="is_active" className="text-sm font-medium text-gray-900">
                                            Account Status
                                        </label>
                                        <p className="text-sm text-gray-500">
                                            {data.is_active
                                                ? 'User can log in and access the system'
                                                : 'User cannot log in to the system'}
                                        </p>
                                    </div>
                                    <label className="relative inline-flex items-center cursor-pointer">
                                        <input
                                            type="checkbox"
                                            id="is_active"
                                            className="sr-only peer"
                                            checked={data.is_active}
                                            onChange={(e) => setData('is_active', e.target.checked)}
                                            disabled={!canDeactivate && user.is_active}
                                        />
                                        <div className={`w-11 h-6 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all ${
                                            data.is_active
                                                ? 'bg-green-600'
                                                : 'bg-gray-300'
                                        } ${!canDeactivate && user.is_active ? 'opacity-50 cursor-not-allowed' : ''}`}></div>
                                    </label>
                                </div>
                                {!canDeactivate && user.is_active && (
                                    <p className="mt-2 text-xs text-amber-600">
                                        This user cannot be deactivated (last admin or current user).
                                    </p>
                                )}
                                {errors.is_active && (
                                    <p className="mt-1 text-sm text-red-600">{errors.is_active}</p>
                                )}
                            </div>

                            {/* Actions */}
                            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pt-4 border-t border-gray-200">
                                <div className="order-2 sm:order-1">
                                    {canDeactivate && user.is_active && (
                                        <button
                                            type="button"
                                            onClick={() => setShowDeactivateModal(true)}
                                            className="text-red-600 hover:text-red-800 text-sm font-medium"
                                        >
                                            Deactivate User
                                        </button>
                                    )}
                                </div>
                                <div className="flex flex-col-reverse sm:flex-row sm:items-center gap-3 order-1 sm:order-2">
                                    <Link href={route('admin.users.index')} className="btn-secondary w-full sm:w-auto text-center">
                                        Cancel
                                    </Link>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="btn-primary w-full sm:w-auto"
                                    >
                                        {processing ? 'Saving...' : 'Save Changes'}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                {/* User Info Card */}
                <div className="card max-w-2xl">
                    <div className="card-header">
                        <h3 className="text-lg font-medium text-gray-900">Account Information</h3>
                    </div>
                    <div className="card-body">
                        <dl className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                            <div>
                                <dt className="text-gray-500">Created</dt>
                                <dd className="text-gray-900">
                                    {new Date(user.created_at).toLocaleDateString('en-US', {
                                        year: 'numeric',
                                        month: 'long',
                                        day: 'numeric',
                                        hour: '2-digit',
                                        minute: '2-digit',
                                    })}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-gray-500">Last Updated</dt>
                                <dd className="text-gray-900">
                                    {new Date(user.updated_at).toLocaleDateString('en-US', {
                                        year: 'numeric',
                                        month: 'long',
                                        day: 'numeric',
                                        hour: '2-digit',
                                        minute: '2-digit',
                                    })}
                                </dd>
                            </div>
                            {user.google_id && (
                                <div className="sm:col-span-2">
                                    <dt className="text-gray-500">Google Account Linked</dt>
                                    <dd className="text-green-600">Yes</dd>
                                </div>
                            )}
                        </dl>
                    </div>
                </div>
            </div>

            {/* Deactivate Confirmation Modal */}
            {showDeactivateModal && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="modal-title"
                >
                    <div
                        className="fixed inset-0 bg-black/50"
                        onClick={() => setShowDeactivateModal(false)}
                        aria-hidden="true"
                    />
                    <div
                        className="relative bg-white rounded-xl shadow-xl max-w-md w-full mx-4 p-6"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div className="flex items-start gap-4">
                            <div className="flex-shrink-0 w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                                <ExclamationTriangleIcon className="w-6 h-6 text-red-600" />
                            </div>
                            <div>
                                <h3 id="modal-title" className="text-lg font-semibold text-gray-900">
                                    Deactivate User
                                </h3>
                                <p className="mt-2 text-sm text-gray-500">
                                    Are you sure you want to deactivate <strong>{user.name}</strong>?
                                    They will no longer be able to log in to the system.
                                </p>
                            </div>
                        </div>
                        <div className="mt-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                            <button
                                type="button"
                                onClick={() => setShowDeactivateModal(false)}
                                className="btn-secondary w-full sm:w-auto"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                onClick={handleDeactivate}
                                className="w-full sm:w-auto px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                            >
                                Deactivate
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
