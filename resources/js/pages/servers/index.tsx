import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Search, RefreshCw } from 'lucide-react';
import CoolifyLayout from '@/layouts/coolify-layout';
import { ServerList } from '@/components/server-status';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import type { Server } from '@/types';

interface ServersIndexProps {
    servers: Server[];
}

export default function ServersIndex({ servers }: ServersIndexProps) {
    const [search, setSearch] = useState('');

    const filteredServers = servers.filter(
        (server) =>
            server.name.toLowerCase().includes(search.toLowerCase()) ||
            server.ip.includes(search)
    );

    return (
        <CoolifyLayout title="Servers">
            <Head title="Servers" />

            <div className="space-y-6">
                {/* Search */}
                <div className="flex items-center gap-4">
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                        <input
                            type="text"
                            placeholder="Search servers..."
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

                {/* Server list */}
                {filteredServers.length === 0 ? (
                    <Card>
                        <div className="py-12 text-center">
                            <p className="text-gray-500 dark:text-gray-400">
                                {search ? 'No servers match your search' : 'No servers found'}
                            </p>
                        </div>
                    </Card>
                ) : (
                    <ServerList servers={filteredServers} />
                )}
            </div>
        </CoolifyLayout>
    );
}
