import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Box, Server, Database, Rocket, RefreshCw, AlertCircle } from 'lucide-react';
import CoolifyLayout from '@/layouts/coolify-layout';
import { StatsCard } from '@/components/stats-card';
import { DeploymentList } from '@/components/deployment-list';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import type { DashboardStats } from '@/types';

interface DashboardProps {
    stats: DashboardStats;
    pollingInterval: number;
}

export default function Dashboard({ stats, pollingInterval }: DashboardProps) {
    const [isRefreshing, setIsRefreshing] = useState(false);

    useEffect(() => {
        if (pollingInterval <= 0) return;

        const interval = setInterval(() => {
            router.reload({ only: ['stats'] });
        }, pollingInterval * 1000);

        return () => clearInterval(interval);
    }, [pollingInterval]);

    const handleRefresh = () => {
        setIsRefreshing(true);
        router.reload({
            only: ['stats'],
            onFinish: () => setIsRefreshing(false),
        });
    };

    return (
        <CoolifyLayout title="Dashboard">
            <Head title="Dashboard" />

            <div className="space-y-8">
                {/* Stats Grid */}
                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <StatsCard
                        title="Applications"
                        value={stats.applications.total}
                        subtitle={`${stats.applications.running} running`}
                        icon={Box}
                        iconClassName="bg-blue-100 dark:bg-blue-900/30"
                    />
                    <StatsCard
                        title="Servers"
                        value={stats.servers.total}
                        subtitle={`${stats.servers.reachable} reachable`}
                        icon={Server}
                        iconClassName="bg-green-100 dark:bg-green-900/30"
                    />
                    <StatsCard
                        title="Databases"
                        value={stats.databases.total}
                        subtitle={`${stats.databases.running} running`}
                        icon={Database}
                        iconClassName="bg-purple-100 dark:bg-purple-900/30"
                    />
                    <StatsCard
                        title="Deployments"
                        value={stats.deployments.total}
                        subtitle="total deployments"
                        icon={Rocket}
                        iconClassName="bg-orange-100 dark:bg-orange-900/30"
                    />
                </div>

                {/* Status Overview */}
                {(stats.applications.error > 0 || stats.servers.unreachable > 0) && (
                    <Card className="border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-900/20">
                        <div className="flex items-center gap-3">
                            <AlertCircle className="size-5 text-red-600 dark:text-red-400" />
                            <div>
                                <p className="font-medium text-red-800 dark:text-red-200">
                                    Issues detected
                                </p>
                                <p className="text-sm text-red-600 dark:text-red-400">
                                    {stats.applications.error > 0 &&
                                        `${stats.applications.error} application${stats.applications.error > 1 ? 's' : ''} in error state. `}
                                    {stats.servers.unreachable > 0 &&
                                        `${stats.servers.unreachable} server${stats.servers.unreachable > 1 ? 's' : ''} unreachable.`}
                                </p>
                            </div>
                        </div>
                    </Card>
                )}

                {/* Recent Deployments */}
                <div>
                    <div className="mb-4 flex items-center justify-between">
                        <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
                            Recent Deployments
                        </h2>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleRefresh}
                            disabled={isRefreshing}
                        >
                            <RefreshCw
                                className={`size-4 ${isRefreshing ? 'animate-spin' : ''}`}
                            />
                            Refresh
                        </Button>
                    </div>
                    <DeploymentList deployments={stats.deployments.recent} />
                </div>
            </div>
        </CoolifyLayout>
    );
}
