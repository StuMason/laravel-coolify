import { Link, usePage } from '@inertiajs/react';
import {
    LayoutDashboard,
    Box,
    Server,
    Database,
    Layers,
    Rocket,
    Settings,
    Moon,
    Sun,
} from 'lucide-react';
import { useState, useEffect } from 'react';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import type { SharedData } from '@/types';

interface CoolifyLayoutProps {
    children: React.ReactNode;
    title?: string;
}

const navigation = [
    { name: 'Dashboard', href: '/coolify', icon: LayoutDashboard },
    { name: 'Applications', href: '/coolify/applications', icon: Box },
    { name: 'Servers', href: '/coolify/servers', icon: Server },
    { name: 'Databases', href: '/coolify/databases', icon: Database },
    { name: 'Services', href: '/coolify/services', icon: Layers },
    { name: 'Deployments', href: '/coolify/deployments', icon: Rocket },
];

export default function CoolifyLayout({ children, title }: CoolifyLayoutProps) {
    const { coolify } = usePage<{ coolify: SharedData['coolify'] }>().props;
    const [isDark, setIsDark] = useState(false);

    useEffect(() => {
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const stored = localStorage.getItem('coolify-theme');
        setIsDark(stored ? stored === 'dark' : prefersDark);
    }, []);

    useEffect(() => {
        document.documentElement.classList.toggle('dark', isDark);
        localStorage.setItem('coolify-theme', isDark ? 'dark' : 'light');
    }, [isDark]);

    const currentPath = window.location.pathname;

    return (
        <div className="min-h-screen bg-gray-50 dark:bg-gray-900">
            {/* Sidebar */}
            <aside className="fixed inset-y-0 left-0 z-50 w-64 border-r border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950">
                <div className="flex h-16 items-center gap-2 border-b border-gray-200 px-6 dark:border-gray-800">
                    <svg
                        className="size-8 text-coolify-500"
                        viewBox="0 0 24 24"
                        fill="currentColor"
                    >
                        <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" />
                    </svg>
                    <span className="text-xl font-bold text-gray-900 dark:text-white">
                        Coolify
                    </span>
                </div>

                <nav className="flex flex-1 flex-col gap-1 p-4">
                    {navigation.map((item) => {
                        const isActive =
                            item.href === '/coolify'
                                ? currentPath === '/coolify'
                                : currentPath.startsWith(item.href);

                        return (
                            <Link
                                key={item.name}
                                href={item.href}
                                className={cn(
                                    'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                                    isActive
                                        ? 'bg-coolify-50 text-coolify-700 dark:bg-coolify-900/30 dark:text-coolify-400'
                                        : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800'
                                )}
                            >
                                <item.icon className="size-5" />
                                {item.name}
                            </Link>
                        );
                    })}
                </nav>

                <div className="border-t border-gray-200 p-4 dark:border-gray-800">
                    <div className="flex items-center justify-between">
                        <span className="text-sm text-gray-500 dark:text-gray-400">
                            {coolify?.appName ?? 'App'}
                        </span>
                        <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => setIsDark(!isDark)}
                            className="size-8"
                        >
                            {isDark ? (
                                <Sun className="size-4" />
                            ) : (
                                <Moon className="size-4" />
                            )}
                        </Button>
                    </div>
                </div>
            </aside>

            {/* Main content */}
            <main className="pl-64">
                {title && (
                    <header className="border-b border-gray-200 bg-white px-8 py-6 dark:border-gray-800 dark:bg-gray-950">
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                            {title}
                        </h1>
                    </header>
                )}
                <div className="p-8">{children}</div>
            </main>
        </div>
    );
}
