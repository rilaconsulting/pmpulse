import { useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import {
    HomeIcon,
    BuildingOfficeIcon,
    BoltIcon,
    Cog6ToothIcon,
    ArrowRightOnRectangleIcon,
    WrenchScrewdriverIcon,
    DocumentTextIcon,
    Bars3Icon,
    MagnifyingGlassIcon,
    XMarkIcon,
} from '@heroicons/react/24/outline';
import PropertySearch from './PropertySearch';
import MobileDrawer from './MobileDrawer';

export default function Layout({ children }) {
    const { auth, flash } = usePage().props;
    const [isDrawerOpen, setIsDrawerOpen] = useState(false);
    const [isMobileSearchOpen, setIsMobileSearchOpen] = useState(false);

    const isAdmin = auth.user?.role?.name === 'admin';

    const navigation = [
        { name: 'Dashboard', routeName: 'dashboard', icon: HomeIcon },
        { name: 'Properties', routeName: 'properties.index', icon: BuildingOfficeIcon },
        { name: 'Utilities', routeName: 'utilities.index', icon: BoltIcon },
        { name: 'Vendors', routeName: 'vendors.index', icon: WrenchScrewdriverIcon },
        ...(isAdmin ? [{ name: 'Admin', routeName: 'admin.users.index', icon: Cog6ToothIcon }] : []),
    ];

    const currentPath = usePage().url;

    return (
        <div className="min-h-screen bg-gray-50">
            {/* Mobile Drawer */}
            <MobileDrawer isOpen={isDrawerOpen} onClose={() => setIsDrawerOpen(false)} />

            {/* Desktop Sidebar - hidden on mobile */}
            <div className="hidden md:block fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-gray-200">
                {/* Logo */}
                <div className="flex items-center h-16 px-6 border-b border-gray-200">
                    <span className="text-xl font-bold text-blue-600">PMPulse</span>
                </div>

                {/* Navigation */}
                <nav className="flex-1 px-4 py-4 space-y-1">
                    {navigation.map((item) => {
                        const href = route(item.routeName);
                        const isActive = currentPath.startsWith(href);
                        return (
                            <Link
                                key={item.name}
                                href={href}
                                className={`flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors ${
                                    isActive
                                        ? 'bg-blue-50 text-blue-700'
                                        : 'text-gray-700 hover:bg-gray-100'
                                }`}
                            >
                                <item.icon className="w-5 h-5 mr-3" />
                                {item.name}
                            </Link>
                        );
                    })}
                </nav>

                {/* User section */}
                <div className="absolute bottom-0 left-0 right-0 border-t border-gray-200">
                    {/* What's New link */}
                    <Link
                        href={route('changelog')}
                        className={`flex items-center px-7 py-3 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-50 transition-colors ${
                            currentPath === route('changelog') ? 'bg-gray-50 text-gray-700' : ''
                        }`}
                    >
                        <DocumentTextIcon className="w-4 h-4 mr-2" />
                        What's New
                    </Link>
                    <div className="p-4 pt-0 flex items-center justify-between">
                        <Link
                            href={route('profile.show')}
                            className="flex items-center hover:bg-gray-50 rounded-lg p-1 -m-1 transition-colors"
                        >
                            <div className="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                <span className="text-sm font-medium text-blue-700">
                                    {auth.user?.name?.charAt(0)?.toUpperCase() || 'U'}
                                </span>
                            </div>
                            <div className="ml-3">
                                <p className="text-sm font-medium text-gray-700">
                                    {auth.user?.name || 'User'}
                                </p>
                                <p className="text-xs text-gray-500">View profile</p>
                            </div>
                        </Link>
                        <Link
                            href={route('logout')}
                            method="post"
                            as="button"
                            className="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100"
                        >
                            <ArrowRightOnRectangleIcon className="w-5 h-5" />
                        </Link>
                    </div>
                </div>
            </div>

            {/* Main content */}
            <div className="md:pl-64">
                {/* Header with search */}
                <header className="sticky top-0 z-40 bg-white border-b border-gray-200">
                    <div className="flex items-center h-16 px-4 md:px-8 gap-4">
                        {/* Mobile hamburger button */}
                        <button
                            type="button"
                            onClick={() => setIsDrawerOpen(true)}
                            className="md:hidden p-2 -ml-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg"
                            aria-label="Open menu"
                        >
                            <Bars3Icon className="w-6 h-6" />
                        </button>

                        {/* Mobile logo */}
                        <span className="md:hidden text-lg font-bold text-blue-600 flex-1">PMPulse</span>

                        {/* Mobile search button */}
                        <button
                            type="button"
                            onClick={() => setIsMobileSearchOpen(true)}
                            className="md:hidden p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg"
                            aria-label="Search properties"
                        >
                            <MagnifyingGlassIcon className="w-6 h-6" />
                        </button>

                        {/* Desktop search - hidden on mobile */}
                        <div className="hidden md:block flex-1 max-w-md">
                            <PropertySearch />
                        </div>
                    </div>

                    {/* Mobile search overlay */}
                    {isMobileSearchOpen && (
                        <div className="md:hidden absolute inset-x-0 top-0 bg-white border-b border-gray-200 p-4 shadow-lg z-50">
                            <div className="flex items-center gap-3">
                                <button
                                    type="button"
                                    onClick={() => setIsMobileSearchOpen(false)}
                                    className="p-2 -ml-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg"
                                    aria-label="Close search"
                                >
                                    <XMarkIcon className="w-6 h-6" />
                                </button>
                                <div className="flex-1">
                                    <PropertySearch autoFocus onNavigate={() => setIsMobileSearchOpen(false)} />
                                </div>
                            </div>
                        </div>
                    )}
                </header>

                {/* Flash messages */}
                {flash?.success && (
                    <div className="mx-4 md:mx-8 mt-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                        <p className="text-sm text-green-700">{flash.success}</p>
                    </div>
                )}
                {flash?.error && (
                    <div className="mx-4 md:mx-8 mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                        <p className="text-sm text-red-700">{flash.error}</p>
                    </div>
                )}

                {/* Page content */}
                <main className="p-4 md:p-8">{children}</main>
            </div>
        </div>
    );
}
