import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Search, RefreshCw, Database as DatabaseIcon } from 'lucide-react';
import CoolifyLayout from '@/layouts/coolify-layout';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import type { Database } from '@/types';

interface DatabasesIndexProps {
    databases: Database[];
}

const databaseTypeColors: Record<string, string> = {
    postgresql: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    mysql: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
    mariadb: 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400',
    mongodb: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
    redis: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    dragonfly: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
    keydb: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
    clickhouse: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
};

export default function DatabasesIndex({ databases }: DatabasesIndexProps) {
    const [search, setSearch] = useState('');
    const [loadingAction, setLoadingAction] = useState<string | null>(null);

    const filteredDatabases = databases.filter(
        (db) =>
            db.name.toLowerCase().includes(search.toLowerCase()) ||
            db.type.toLowerCase().includes(search.toLowerCase())
    );

    const handleRestart = (uuid: string) => {
        setLoadingAction(uuid);
        router.post(
            `/coolify/api/databases/${uuid}/restart`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setLoadingAction(null),
            }
        );
    };

    return (
        <CoolifyLayout title="Databases">
            <Head title="Databases" />

            <div className="space-y-6">
                {/* Search */}
                <div className="flex items-center gap-4">
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                        <input
                            type="text"
                            placeholder="Search databases..."
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

                {/* Database list */}
                {filteredDatabases.length === 0 ? (
                    <Card>
                        <div className="py-12 text-center">
                            <p className="text-gray-500 dark:text-gray-400">
                                {search
                                    ? 'No databases match your search'
                                    : 'No databases found'}
                            </p>
                        </div>
                    </Card>
                ) : (
                    <div className="space-y-4">
                        {filteredDatabases.map((database) => (
                            <Card key={database.uuid}>
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-4">
                                        <div className="flex size-12 shrink-0 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                                            <DatabaseIcon className="size-6 text-gray-600 dark:text-gray-400" />
                                        </div>
                                        <div>
                                            <div className="flex items-center gap-3">
                                                <Link
                                                    href={`/coolify/databases/${database.uuid}`}
                                                    className="font-semibold text-gray-900 hover:text-coolify-600 dark:text-white dark:hover:text-coolify-400"
                                                >
                                                    {database.name}
                                                </Link>
                                                <StatusBadge status={database.status} />
                                                <span
                                                    className={`rounded px-2 py-0.5 text-xs font-medium ${databaseTypeColors[database.type] ?? 'bg-gray-100 text-gray-700'}`}
                                                >
                                                    {database.type}
                                                </span>
                                            </div>
                                            {database.description && (
                                                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                    {database.description}
                                                </p>
                                            )}
                                            <div className="mt-2 flex items-center gap-4 text-sm text-gray-500 dark:text-gray-400">
                                                {database.is_public && (
                                                    <Badge variant="info">
                                                        Public: {database.public_port}
                                                    </Badge>
                                                )}
                                            </div>
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => handleRestart(database.uuid)}
                                            disabled={loadingAction === database.uuid}
                                        >
                                            <RefreshCw className="size-4" />
                                            Restart
                                        </Button>
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
