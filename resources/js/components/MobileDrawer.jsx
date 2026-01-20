import { Fragment } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { Dialog, Transition } from '@headlessui/react';
import {
    HomeIcon,
    BuildingOfficeIcon,
    BoltIcon,
    Cog6ToothIcon,
    ArrowRightOnRectangleIcon,
    WrenchScrewdriverIcon,
    DocumentTextIcon,
    XMarkIcon,
} from '@heroicons/react/24/outline';

export default function MobileDrawer({ isOpen, onClose }) {
    const { auth } = usePage().props;
    const currentPath = usePage().url;

    const isAdmin = auth.user?.role?.name === 'admin';

    const navigation = [
        { name: 'Dashboard', routeName: 'dashboard', icon: HomeIcon },
        { name: 'Properties', routeName: 'properties.index', icon: BuildingOfficeIcon },
        { name: 'Utilities', routeName: 'utilities.index', icon: BoltIcon },
        { name: 'Vendors', routeName: 'vendors.index', icon: WrenchScrewdriverIcon },
        ...(isAdmin ? [{ name: 'Admin', routeName: 'admin.users.index', icon: Cog6ToothIcon }] : []),
    ];

    return (
        <Transition show={isOpen} as={Fragment}>
            <Dialog onClose={onClose} className="relative z-50">
                {/* Backdrop */}
                <Transition.Child
                    as={Fragment}
                    enter="ease-out duration-300"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in duration-200"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="fixed inset-0 bg-black/30" aria-hidden="true" />
                </Transition.Child>

                {/* Drawer panel */}
                <Transition.Child
                    as={Fragment}
                    enter="ease-out duration-300"
                    enterFrom="-translate-x-full"
                    enterTo="translate-x-0"
                    leave="ease-in duration-200"
                    leaveFrom="translate-x-0"
                    leaveTo="-translate-x-full"
                >
                    <Dialog.Panel className="fixed inset-y-0 left-0 w-72 bg-white shadow-xl flex flex-col">
                        {/* Header with close button */}
                        <div className="flex items-center justify-between h-16 px-6 border-b border-gray-200">
                            <span className="text-xl font-bold text-blue-600">PMPulse</span>
                            <button
                                type="button"
                                onClick={onClose}
                                className="p-2 -mr-2 min-w-[44px] min-h-[44px] flex items-center justify-center text-gray-400 hover:text-gray-600 active:text-gray-800 rounded-lg hover:bg-gray-100 active:bg-gray-200"
                                aria-label="Close menu"
                            >
                                <XMarkIcon className="w-6 h-6" />
                            </button>
                        </div>

                        {/* Navigation */}
                        <nav className="flex-1 px-4 py-4 space-y-1 overflow-y-auto">
                            {navigation.map((item) => {
                                const href = route(item.routeName);
                                const isActive = currentPath.startsWith(href);
                                return (
                                    <Link
                                        key={item.name}
                                        href={href}
                                        onClick={onClose}
                                        className={`flex items-center px-3 py-3 text-base font-medium rounded-lg transition-colors ${
                                            isActive
                                                ? 'bg-blue-50 text-blue-700'
                                                : 'text-gray-700 hover:bg-gray-100 active:bg-gray-200'
                                        }`}
                                    >
                                        <item.icon className="w-6 h-6 mr-3" />
                                        {item.name}
                                    </Link>
                                );
                            })}
                        </nav>

                        {/* User section */}
                        <div className="border-t border-gray-200">
                            {/* What's New link */}
                            <Link
                                href={route('changelog')}
                                onClick={onClose}
                                className={`flex items-center px-7 py-4 text-base text-gray-500 hover:text-gray-700 hover:bg-gray-50 active:bg-gray-100 transition-colors ${
                                    currentPath === route('changelog') ? 'bg-gray-50 text-gray-700' : ''
                                }`}
                            >
                                <DocumentTextIcon className="w-5 h-5 mr-2" />
                                What's New
                            </Link>
                            <div className="p-4 pt-0 flex items-center justify-between">
                                <Link
                                    href={route('profile.show')}
                                    onClick={onClose}
                                    className="flex items-center hover:bg-gray-50 active:bg-gray-100 rounded-lg p-2 -m-2 transition-colors flex-1"
                                >
                                    <div className="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                        <span className="text-base font-medium text-blue-700">
                                            {auth.user?.name?.charAt(0)?.toUpperCase() || 'U'}
                                        </span>
                                    </div>
                                    <div className="ml-3">
                                        <p className="text-base font-medium text-gray-700">
                                            {auth.user?.name || 'User'}
                                        </p>
                                        <p className="text-sm text-gray-500">View profile</p>
                                    </div>
                                </Link>
                                <Link
                                    href={route('logout')}
                                    method="post"
                                    as="button"
                                    onClick={onClose}
                                    className="p-3 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 active:bg-gray-200"
                                    aria-label="Logout"
                                >
                                    <ArrowRightOnRectangleIcon className="w-6 h-6" />
                                </Link>
                            </div>
                        </div>
                    </Dialog.Panel>
                </Transition.Child>
            </Dialog>
        </Transition>
    );
}
