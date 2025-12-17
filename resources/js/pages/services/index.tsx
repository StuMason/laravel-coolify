import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Search, RefreshCw, Layers, Play, Square } from 'lucide-react';
import CoolifyLayout from '@/layouts/coolify-layout';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { StatusBadge } from '@/components/status-badge';
import type { Service } from '@/types';

interface ServicesIndexProps {
    services: Service[];
}

export default function ServicesIndex({ services }: ServicesIndexProps) {
    const [search, setSearch] = useState('');
    const [loadingAction, setLoadingAction] = useState<string | null>(null);

    const filteredServices = services.filter(
        (service) =>
            service.name.toLowerCase().includes(search.toLowerCase()) ||
            service.type.toLowerCase().includes(search.toLowerCase())
    );

    const handleAction = (action: 'start' | 'stop' | 'restart', uuid: string) => {
        setLoadingAction(`${uuid}-${action}`);
        router.post(
            `/coolify/api/services/${uuid}/${action}`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setLoadingAction(null),
            }
        );
    };

    const isRunning = (status: string) =>
        status.toLowerCase() === 'running' || status.toLowerCase() === 'healthy';

    return (
        <CoolifyLayout title="Services">
            <Head title="Services" />

            <div className="space-y-6">
                {/* Search */}
                <div className="flex items-center gap-4">
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                        <input
                            type="text"
                            placeholder="Search services..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full rounded-lg border border-gray-200 bg-white py-2 pl-10 pr-4 text-sm focus:border-coolify-500 focus:outline-none focus:ring-1 focus:ring-coolify-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        />
                    </div>
                    <Button variant="outline" onClick={() => router.reload()}>
                        <RefreshCw className="size-4" />
                        Refresh
                    </Button>
                </div>

                {/* Service list */}
                {filteredServices.length === 0 ? (
                    <Card>
                        <div className="py-12 text-center">
                            <p className="text-gray-500 dark:text-gray-400">
                                {search
                                    ? 'No services match your search'
                                    : 'No services found'}
                            </p>
                        </div>
                    </Card>
                ) : (
                    <div className="space-y-4">
                        {filteredServices.map((service) => (
                            <Card key={service.uuid}>
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-4">
                                        <div className="flex size-12 shrink-0 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                                            <Layers className="size-6 text-gray-600 dark:text-gray-400" />
                                        </div>
                                        <div>
                                            <div className="flex items-center gap-3">
                                                <Link
                                                    href={`/coolify/services/${service.uuid}`}
                                                    className="font-semibold text-gray-900 hover:text-coolify-600 dark:text-white dark:hover:text-coolify-400"
                                                >
                                                    {service.name}
                                                </Link>
                                                <StatusBadge status={service.status} />
                                                <span className="rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                                    {service.type}
                                                </span>
                                            </div>
                                            {service.description && (
                                                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                    {service.description}
                                                </p>
                                            )}
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-2">
                                        {!isRunning(service.status) && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    handleAction('start', service.uuid)
                                                }
                                                disabled={
                                                    loadingAction === `${service.uuid}-start`
                                                }
                                            >
                                                <Play className="size-4" />
                                                Start
                                            </Button>
                                        )}
                                        {isRunning(service.status) && (
                                            <>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        handleAction('stop', service.uuid)
                                                    }
                                                    disabled={
                                                        loadingAction === `${service.uuid}-stop`
                                                    }
                                                >
                                                    <Square className="size-4" />
                                                    Stop
                                                </Button>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        handleAction('restart', service.uuid)
                                                    }
                                                    disabled={
                                                        loadingAction ===
                                                        `${service.uuid}-restart`
                                                    }
                                                >
                                                    <RefreshCw className="size-4" />
                                                    Restart
                                                </Button>
                                            </>
                                        )}
                                    </div>
                                </div>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </CoolifyLayout>
    );
}
