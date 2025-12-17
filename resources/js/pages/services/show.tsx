import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowLeft, Layers, Play, Square, RefreshCw } from 'lucide-react';
import CoolifyLayout from '@/layouts/coolify-layout';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { formatDate } from '@/lib/utils';
import type { Service } from '@/types';

interface ServiceShowProps {
    service: Service;
}

export default function ServiceShow({ service }: ServiceShowProps) {
    const [loadingAction, setLoadingAction] = useState<string | null>(null);

    const handleAction = (action: 'start' | 'stop' | 'restart') => {
        setLoadingAction(action);
        router.post(
            `/coolify/api/services/${service.uuid}/${action}`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setLoadingAction(null),
            }
        );
    };

    const isRunning =
        service.status.toLowerCase() === 'running' ||
        service.status.toLowerCase() === 'healthy';
    const isStopped = service.status.toLowerCase() === 'stopped';

    return (
        <CoolifyLayout>
            <Head title={service.name} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div>
                        <Link
                            href="/coolify/services"
                            className="mb-2 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                        >
                            <ArrowLeft className="size-4" />
                            Back to Services
                        </Link>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                                {service.name}
                            </h1>
                            <StatusBadge status={service.status} />
                            <Badge variant="default">{service.type}</Badge>
                        </div>
                        {service.description && (
                            <p className="mt-1 text-gray-500 dark:text-gray-400">
                                {service.description}
                            </p>
                        )}
                    </div>

                    <div className="flex items-center gap-2">
                        {isStopped && (
                            <Button
                                variant="outline"
                                onClick={() => handleAction('start')}
                                disabled={!!loadingAction}
                            >
                                <Play className="size-4" />
                                Start
                            </Button>
                        )}
                        {isRunning && (
                            <>
                                <Button
                                    variant="outline"
                                    onClick={() => handleAction('stop')}
                                    disabled={!!loadingAction}
                                >
                                    <Square className="size-4" />
                                    Stop
                                </Button>
                                <Button
                                    variant="outline"
                                    onClick={() => handleAction('restart')}
                                    disabled={!!loadingAction}
                                >
                                    <RefreshCw
                                        className={`size-4 ${loadingAction === 'restart' ? 'animate-spin' : ''}`}
                                    />
                                    Restart
                                </Button>
                            </>
                        )}
                    </div>
                </div>

                {/* Info Cards */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <Card>
                        <h3 className="mb-4 font-semibold text-gray-900 dark:text-white">
                            Details
                        </h3>
                        <dl className="space-y-3 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-gray-500 dark:text-gray-400">UUID</dt>
                                <dd className="font-mono text-gray-900 dark:text-white">
                                    {service.uuid.slice(0, 8)}
                                </dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-500 dark:text-gray-400">Type</dt>
                                <dd className="text-gray-900 dark:text-white">
                                    {service.type}
                                </dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-500 dark:text-gray-400">Status</dt>
                                <dd>
                                    <StatusBadge status={service.status} />
                                </dd>
                            </div>
                        </dl>
                    </Card>

                    <Card>
                        <h3 className="mb-4 font-semibold text-gray-900 dark:text-white">
                            Timestamps
                        </h3>
                        <dl className="space-y-3 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-gray-500 dark:text-gray-400">Created</dt>
                                <dd className="text-gray-900 dark:text-white">
                                    {formatDate(service.created_at)}
                                </dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-500 dark:text-gray-400">Updated</dt>
                                <dd className="text-gray-900 dark:text-white">
                                    {formatDate(service.updated_at)}
                                </dd>
                            </div>
                        </dl>
                    </Card>
                </div>
            </div>
        </CoolifyLayout>
    );
}
