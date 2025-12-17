import { Head, router } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import CoolifyLayout from '@/layouts/coolify-layout';
import { DeploymentList } from '@/components/deployment-list';
import { Button } from '@/components/ui/button';
import type { Deployment } from '@/types';

interface DeploymentsIndexProps {
    deployments: Deployment[];
}

export default function DeploymentsIndex({ deployments }: DeploymentsIndexProps) {
    return (
        <CoolifyLayout title="Deployments">
            <Head title="Deployments" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-end">
                    <Button variant="outline" onClick={() => router.reload()}>
                        <RefreshCw className="size-4" />
                        Refresh
                    </Button>
                </div>

                {/* Deployments list */}
                <DeploymentList deployments={deployments} showApplication />
            </div>
        </CoolifyLayout>
    );
}
