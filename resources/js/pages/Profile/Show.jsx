import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import Layout from '../../components/Layout';
import { EyeIcon, EyeSlashIcon, UserCircleIcon, KeyIcon } from '@heroicons/react/24/outline';

export default function Show({ user }) {
    const [showCurrentPassword, setShowCurrentPassword] = useState(false);
    const [showNewPassword, setShowNewPassword] = useState(false);

    // Profile form
    const profileForm = useForm({
        name: user.name || '',
    });

    // Password form
    const passwordForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const handleProfileSubmit = (e) => {
        e.preventDefault();
        profileForm.patch('/profile');
    };

    const handlePasswordSubmit = (e) => {
        e.preventDefault();
        passwordForm.put('/profile/password', {
            onSuccess: () => {
                passwordForm.reset();
            },
        });
    };

    const isPasswordUser = user.auth_provider === 'password';

    return (
        <Layout>
            <Head title="Profile" />

            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Profile</h1>
                    <p className="mt-1 text-sm text-gray-500">
                        Manage your account settings and profile information
                    </p>
                </div>

                {/* Profile Overview Card */}
                <div className="card">
                    <div className="card-body">
                        <div className="flex items-center gap-6">
                            <div className="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center">
                                <span className="text-2xl font-bold text-blue-700">
                                    {user.name?.charAt(0)?.toUpperCase() || 'U'}
                                </span>
                            </div>
                            <div>
                                <h2 className="text-xl font-semibold text-gray-900">{user.name}</h2>
                                <p className="text-gray-500">{user.email}</p>
                                <div className="mt-2 flex items-center gap-2">
                                    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                        user.role?.name === 'admin'
                                            ? 'bg-purple-100 text-purple-800'
                                            : 'bg-gray-100 text-gray-800'
                                    }`}>
                                        {user.role?.name ? user.role.name.charAt(0).toUpperCase() + user.role.name.slice(1) : 'No Role'}
                                    </span>
                                    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                        user.auth_provider === 'google'
                                            ? 'bg-blue-100 text-blue-800'
                                            : 'bg-gray-100 text-gray-800'
                                    }`}>
                                        {user.auth_provider === 'google' ? 'Google SSO' : 'Password'}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Update Profile Card */}
                    <div className="card">
                        <div className="card-header">
                            <div className="flex items-center gap-3">
                                <UserCircleIcon className="w-5 h-5 text-gray-400" />
                                <h3 className="text-lg font-medium text-gray-900">Profile Information</h3>
                            </div>
                            <p className="mt-1 text-sm text-gray-500">
                                Update your account's profile information.
                            </p>
                        </div>
                        <div className="card-body">
                            <form onSubmit={handleProfileSubmit} className="space-y-4">
                                <div>
                                    <label htmlFor="name" className="label">
                                        Full Name
                                    </label>
                                    <input
                                        type="text"
                                        id="name"
                                        className="input"
                                        value={profileForm.data.name}
                                        onChange={(e) => profileForm.setData('name', e.target.value)}
                                        required
                                    />
                                    {profileForm.errors.name && (
                                        <p className="mt-1 text-sm text-red-600">{profileForm.errors.name}</p>
                                    )}
                                </div>

                                <div>
                                    <label htmlFor="email" className="label">
                                        Email Address
                                    </label>
                                    <input
                                        type="email"
                                        id="email"
                                        className="input bg-gray-50"
                                        value={user.email}
                                        disabled
                                    />
                                    <p className="mt-1 text-xs text-gray-500">
                                        Email address cannot be changed. Contact an administrator if needed.
                                    </p>
                                </div>

                                <div className="pt-2">
                                    <button
                                        type="submit"
                                        disabled={profileForm.processing}
                                        className="btn-primary"
                                    >
                                        {profileForm.processing ? 'Saving...' : 'Save Changes'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    {/* Update Password Card */}
                    <div className="card">
                        <div className="card-header">
                            <div className="flex items-center gap-3">
                                <KeyIcon className="w-5 h-5 text-gray-400" />
                                <h3 className="text-lg font-medium text-gray-900">Update Password</h3>
                            </div>
                            <p className="mt-1 text-sm text-gray-500">
                                {isPasswordUser
                                    ? 'Ensure your account is using a secure password.'
                                    : 'Password management is not available for SSO users.'}
                            </p>
                        </div>
                        <div className="card-body">
                            {isPasswordUser ? (
                                <form onSubmit={handlePasswordSubmit} className="space-y-4">
                                    <div>
                                        <label htmlFor="current_password" className="label">
                                            Current Password
                                        </label>
                                        <div className="relative">
                                            <input
                                                type={showCurrentPassword ? 'text' : 'password'}
                                                id="current_password"
                                                className="input pr-10"
                                                value={passwordForm.data.current_password}
                                                onChange={(e) => passwordForm.setData('current_password', e.target.value)}
                                                required
                                            />
                                            <button
                                                type="button"
                                                onClick={() => setShowCurrentPassword(!showCurrentPassword)}
                                                className="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-gray-600"
                                                aria-label={showCurrentPassword ? 'Hide current password' : 'Show current password'}
                                            >
                                                {showCurrentPassword ? (
                                                    <EyeSlashIcon className="w-5 h-5" />
                                                ) : (
                                                    <EyeIcon className="w-5 h-5" />
                                                )}
                                            </button>
                                        </div>
                                        {passwordForm.errors.current_password && (
                                            <p className="mt-1 text-sm text-red-600">{passwordForm.errors.current_password}</p>
                                        )}
                                    </div>

                                    <div>
                                        <label htmlFor="password" className="label">
                                            New Password
                                        </label>
                                        <div className="relative">
                                            <input
                                                type={showNewPassword ? 'text' : 'password'}
                                                id="password"
                                                className="input pr-10"
                                                value={passwordForm.data.password}
                                                onChange={(e) => passwordForm.setData('password', e.target.value)}
                                                required
                                            />
                                            <button
                                                type="button"
                                                onClick={() => setShowNewPassword(!showNewPassword)}
                                                className="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-gray-600"
                                                aria-label={showNewPassword ? 'Hide new password' : 'Show new password'}
                                            >
                                                {showNewPassword ? (
                                                    <EyeSlashIcon className="w-5 h-5" />
                                                ) : (
                                                    <EyeIcon className="w-5 h-5" />
                                                )}
                                            </button>
                                        </div>
                                        <p className="mt-1 text-xs text-gray-500">
                                            Minimum 8 characters, with mixed case and numbers
                                        </p>
                                        {passwordForm.errors.password && (
                                            <p className="mt-1 text-sm text-red-600">{passwordForm.errors.password}</p>
                                        )}
                                    </div>

                                    <div>
                                        <label htmlFor="password_confirmation" className="label">
                                            Confirm New Password
                                        </label>
                                        <input
                                            type="password"
                                            id="password_confirmation"
                                            className="input"
                                            value={passwordForm.data.password_confirmation}
                                            onChange={(e) => passwordForm.setData('password_confirmation', e.target.value)}
                                            required
                                        />
                                    </div>

                                    <div className="pt-2">
                                        <button
                                            type="submit"
                                            disabled={passwordForm.processing}
                                            className="btn-primary"
                                        >
                                            {passwordForm.processing ? 'Updating...' : 'Update Password'}
                                        </button>
                                    </div>
                                </form>
                            ) : (
                                <div className="py-8 text-center">
                                    <div className="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <svg className="w-6 h-6 text-blue-600" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12.545,10.239v3.821h5.445c-0.712,2.315-2.647,3.972-5.445,3.972c-3.332,0-6.033-2.701-6.033-6.032s2.701-6.032,6.033-6.032c1.498,0,2.866,0.549,3.921,1.453l2.814-2.814C17.503,2.988,15.139,2,12.545,2C7.021,2,2.543,6.477,2.543,12s4.478,10,10.002,10c8.396,0,10.249-7.85,9.426-11.748L12.545,10.239z"/>
                                        </svg>
                                    </div>
                                    <p className="text-gray-600">
                                        Your account uses Google Single Sign-On.
                                    </p>
                                    <p className="mt-1 text-sm text-gray-500">
                                        Password management is handled by your Google account.
                                    </p>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Account Details Card */}
                <div className="card">
                    <div className="card-header">
                        <h3 className="text-lg font-medium text-gray-900">Account Details</h3>
                    </div>
                    <div className="card-body">
                        <dl className="grid grid-cols-1 gap-4 sm:grid-cols-3 text-sm">
                            <div>
                                <dt className="text-gray-500">Account Created</dt>
                                <dd className="mt-1 text-gray-900">
                                    {new Date(user.created_at).toLocaleDateString('en-US', {
                                        year: 'numeric',
                                        month: 'long',
                                        day: 'numeric',
                                    })}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-gray-500">Last Updated</dt>
                                <dd className="mt-1 text-gray-900">
                                    {new Date(user.updated_at).toLocaleDateString('en-US', {
                                        year: 'numeric',
                                        month: 'long',
                                        day: 'numeric',
                                    })}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-gray-500">Account Status</dt>
                                <dd className="mt-1">
                                    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                        user.is_active
                                            ? 'bg-green-100 text-green-800'
                                            : 'bg-red-100 text-red-800'
                                    }`}>
                                        {user.is_active ? 'Active' : 'Inactive'}
                                    </span>
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </Layout>
    );
}
