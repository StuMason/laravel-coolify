import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Search, RefreshCw } from 'lucide-react';
import CoolifyLayout from '@/layouts/coolify-layout';
import { ApplicationCard } from '@/components/application-card';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import type { Application } from '@/types';

interface ApplicationsIndexProps {
    applications: Application[];
}

export default function ApplicationsIndex({ applications }: ApplicationsIndexProps) {
    const [search, setSearch] = useState('');
    const [loadingAction, setLoadingAction] = useState<string | null>(null);

    const filteredApplications = applications.filter(
        (app) =>
            app.name.toLowerCase().includes(search.toLowerCase()) ||
            app.description?.toLowerCase().includes(search.toLowerCase())
    );

    const handleAction = async (
        action: 'deploy' | 'restart' | 'stop' | 'start',
        uuid: string
    ) => {
        setLoadingAction(uuid);
        router.post(
            `/coolify/api/applications/${uuid}/${action}`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setLoadingAction(null),
            }
        );
    };

    return (
        <CoolifyLayout title="Applications">
            <Head title="Applications" />

            <div className="space-y-6">
                {/* Search and filters */}
                <div className="flex items-center gap-4">
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                        <input
                            type="text"
                            placeholder="Search applications..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full rounded-lg border border-gray-200 bg-white py-2 pl-10 pr-4 text-sm focus:border-coolify-500 focus:outline-none focus:ring-1 focus:ring-coolify-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        />
                    </div>
                    <Button
                        variant="outline"
                        onClick={() => router.reload()}
                    >
                        <RefreshCw className="size-4" />
                        Refresh
                    </Button>
                </div>

                {/* Applications list */}
                {filteredApplications.length === 0 ? (
                    <Card>
                        <div className="py-12 text-center">
                            <p className="text-gray-500 dark:text-gray-400">
                                {search
                                    ? 'No applications match your search'
                                    : 'No applications found'}
                            </p>
                        </div>
                    </Card>
                ) : (
                    <div className="space-y-4">
                        {filteredApplications.map((application) => (
                            <ApplicationCard
                                key={application.uuid}
                                application={application}
                                onDeploy={(uuid) => handleAction('deploy', uuid)}
                                onRestart={(uuid) => handleAction('restart', uuid)}
                                onStop={(uuid) => handleAction('stop', uuid)}
                                onStart={(uuid) => handleAction('start', uuid)}
                                isLoading={loadingAction === application.uuid}
                            />
                        ))}
                    </div>
                )}
            </div>
        </CoolifyLayout>
    );
}
