import { Link } from '@inertiajs/react';
import { Server as ServerIcon, CheckCircle, XCircle } from 'lucide-react';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { Server } from '@/types';

interface ServerStatusProps {
    server: Server;
}

export function ServerStatus({ server }: ServerStatusProps) {
    return (
        <Card className="p-4">
            <div className="flex items-center gap-4">
                <div
                    className={cn(
                        'flex size-10 shrink-0 items-center justify-center rounded-full',
                        server.is_reachable
                            ? 'bg-green-100 dark:bg-green-900/30'
                            : 'bg-red-100 dark:bg-red-900/30'
                    )}
                >
                    <ServerIcon
                        className={cn(
                            'size-5',
                            server.is_reachable
                                ? 'text-green-600 dark:text-green-400'
                                : 'text-red-600 dark:text-red-400'
                        )}
                    />
                </div>

                <div className="min-w-0 flex-1">
                    <Link
                        href={`/coolify/servers/${server.uuid}`}
                        className="font-medium text-gray-900 hover:text-coolify-600 dark:text-white dark:hover:text-coolify-400"
                    >
                        {server.name}
                    </Link>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        {server.ip}
                        {server.port && server.port !== 22 && `:${server.port}`}
                    </p>
                </div>

                <div className="flex shrink-0 items-center gap-2">
                    {server.is_reachable ? (
                        <span className="flex items-center gap-1 text-sm text-green-600 dark:text-green-400">
                            <CheckCircle className="size-4" />
                            Reachable
                        </span>
                    ) : (
                        <span className="flex items-center gap-1 text-sm text-red-600 dark:text-red-400">
                            <XCircle className="size-4" />
                            Unreachable
                        </span>
                    )}
                </div>
            </div>
        </Card>
    );
}

interface ServerListProps {
    servers: Server[];
}

export function ServerList({ servers }: ServerListProps) {
    if (servers.length === 0) {
        return (
            <Card>
                <div className="py-8 text-center text-gray-500 dark:text-gray-400">
                    No servers found
                </div>
            </Card>
        );
    }

    return (
        <div className="space-y-3">
            {servers.map((server) => (
                <ServerStatus key={server.uuid} server={server} />
            ))}
        </div>
    );
}
