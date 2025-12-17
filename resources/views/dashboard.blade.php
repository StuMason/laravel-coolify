@extends('coolify::layout')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8" x-data="dashboard()" x-init="init()">
    <!-- Status Banner -->
    <div x-show="!stats.connected" x-cloak class="mb-6 rounded-lg bg-yellow-50 p-4 dark:bg-yellow-900/20">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Not Connected</h3>
                <p class="mt-1 text-sm text-yellow-700 dark:text-yellow-300">
                    Unable to connect to Coolify. Check your <code class="rounded bg-yellow-100 px-1 dark:bg-yellow-800">COOLIFY_URL</code> and <code class="rounded bg-yellow-100 px-1 dark:bg-yellow-800">COOLIFY_TOKEN</code> configuration.
                </p>
            </div>
        </div>
    </div>

    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Infrastructure Dashboard</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Monitor and manage your Coolify resources</p>
    </div>

    <!-- Application Card -->
    <div class="mb-8" x-show="stats.application" x-cloak>
        <div class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-coolify-100 dark:bg-coolify-900">
                                <svg class="h-6 w-6 text-coolify-600 dark:text-coolify-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-16.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a4.5 4.5 0 01.9-2.7L5.737 5.1a3.375 3.375 0 012.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 01.9 2.7m0 0a3 3 0 01-3 3m0 3h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008zm-3 6h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white" x-text="stats.application?.name || 'Application'"></h3>
                            <div class="mt-1 flex items-center space-x-4">
                                <span class="inline-flex items-center text-sm" :class="statusClass(stats.application?.status)">
                                    <span class="mr-1.5 h-2 w-2 rounded-full" :class="statusDotClass(stats.application?.status)"></span>
                                    <span x-text="stats.application?.status || 'unknown'"></span>
                                </span>
                                <span class="text-sm text-gray-500 dark:text-gray-400" x-text="stats.application?.fqdn"></span>
                            </div>
                        </div>
                    </div>
                    <div class="flex space-x-2">
                        <button @click="deployApp()" :disabled="deploying" class="inline-flex items-center rounded-md bg-coolify-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-coolify-500 disabled:opacity-50">
                            <svg class="mr-1.5 h-4 w-4" :class="{ 'animate-spin': deploying }" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/>
                            </svg>
                            <span x-text="deploying ? 'Deploying...' : 'Deploy'"></span>
                        </button>
                        <button @click="restartApp()" :disabled="restarting" class="inline-flex items-center rounded-md bg-gray-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-500 disabled:opacity-50">
                            <svg class="mr-1.5 h-4 w-4" :class="{ 'animate-spin': restarting }" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.636 5.636a9 9 0 1012.728 0M12 3v9"/>
                            </svg>
                            <span x-text="restarting ? 'Restarting...' : 'Restart'"></span>
                        </button>
                    </div>
                </div>

                <!-- Git Info -->
                <div class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700" x-show="stats.application?.repository">
                    <dl class="grid grid-cols-1 gap-x-4 gap-y-2 sm:grid-cols-3">
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Repository</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white" x-text="stats.application?.repository"></dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Branch</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white" x-text="stats.application?.branch"></dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Commit</dt>
                            <dd class="mt-1 font-mono text-sm text-gray-900 dark:text-white" x-text="stats.application?.commit"></dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <!-- Resources Grid -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <!-- Database Card -->
        <template x-for="(db, key) in stats.databases" :key="key">
            <div class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900">
                                <svg class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125"/>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-white" x-text="db.name"></h4>
                            <div class="mt-1 flex items-center">
                                <span class="inline-flex items-center text-xs" :class="statusClass(db.status)">
                                    <span class="mr-1 h-1.5 w-1.5 rounded-full" :class="statusDotClass(db.status)"></span>
                                    <span x-text="db.status"></span>
                                </span>
                                <span class="ml-2 text-xs text-gray-500 dark:text-gray-400" x-text="db.type"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- Recent Deployments -->
    <div class="mt-8" x-show="stats.recentDeployments?.length > 0" x-cloak>
        <h2 class="mb-4 text-lg font-medium text-gray-900 dark:text-white">Recent Deployments</h2>
        <div class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
            <ul role="list" class="divide-y divide-gray-200 dark:divide-gray-700">
                <template x-for="deployment in stats.recentDeployments" :key="deployment.uuid">
                    <li class="px-4 py-4 sm:px-6">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <span class="inline-flex items-center text-sm" :class="statusClass(deployment.status)">
                                    <span class="mr-2 h-2 w-2 rounded-full" :class="statusDotClass(deployment.status)"></span>
                                    <span x-text="deployment.status"></span>
                                </span>
                                <span class="ml-4 font-mono text-sm text-gray-500 dark:text-gray-400" x-text="deployment.commit"></span>
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400" x-text="formatDate(deployment.created_at)"></div>
                        </div>
                    </li>
                </template>
            </ul>
        </div>
    </div>

    <!-- Empty State -->
    <div x-show="stats.connected && !stats.application && Object.keys(stats.databases).length === 0" x-cloak class="text-center py-12">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125"/>
        </svg>
        <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">No resources configured</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Set <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">COOLIFY_APPLICATION_UUID</code> in your .env to get started.
        </p>
        <div class="mt-6">
            <a href="https://coolify.io/docs" target="_blank" class="text-sm font-semibold text-coolify-600 hover:text-coolify-500">
                View documentation <span aria-hidden="true">&rarr;</span>
            </a>
        </div>
    </div>
</div>

@push('scripts')
<script>
function dashboard() {
    return {
        stats: {
            connected: false,
            application: null,
            databases: {},
            recentDeployments: []
        },
        deploying: false,
        restarting: false,
        pollInterval: {{ config('coolify.polling_interval', 10) * 1000 }},

        async init() {
            await this.fetchStats();
            if (this.pollInterval > 0) {
                setInterval(() => this.fetchStats(), this.pollInterval);
            }
        },

        async fetchStats() {
            try {
                const response = await fetch('{{ route("coolify.stats") }}');
                this.stats = await response.json();
            } catch (error) {
                console.error('Failed to fetch stats:', error);
            }
        },

        async deployApp() {
            if (!this.stats.application?.uuid) return;
            this.deploying = true;
            try {
                await fetch(`{{ url(config('coolify.path')) }}/api/applications/${this.stats.application.uuid}/deploy`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Content-Type': 'application/json'
                    }
                });
                await this.fetchStats();
            } catch (error) {
                console.error('Deploy failed:', error);
            } finally {
                this.deploying = false;
            }
        },

        async restartApp() {
            if (!this.stats.application?.uuid) return;
            this.restarting = true;
            try {
                await fetch(`{{ url(config('coolify.path')) }}/api/applications/${this.stats.application.uuid}/restart`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Content-Type': 'application/json'
                    }
                });
                await this.fetchStats();
            } catch (error) {
                console.error('Restart failed:', error);
            } finally {
                this.restarting = false;
            }
        },

        statusClass(status) {
            const s = (status || '').toLowerCase();
            if (s === 'running' || s === 'finished' || s === 'success') return 'text-green-600 dark:text-green-400';
            if (s === 'stopped' || s === 'failed' || s === 'error') return 'text-red-600 dark:text-red-400';
            if (s === 'deploying' || s === 'starting' || s === 'restarting' || s === 'in_progress') return 'text-blue-600 dark:text-blue-400';
            return 'text-gray-600 dark:text-gray-400';
        },

        statusDotClass(status) {
            const s = (status || '').toLowerCase();
            if (s === 'running' || s === 'finished' || s === 'success') return 'bg-green-500';
            if (s === 'stopped' || s === 'failed' || s === 'error') return 'bg-red-500';
            if (s === 'deploying' || s === 'starting' || s === 'restarting' || s === 'in_progress') return 'bg-blue-500 animate-pulse';
            return 'bg-gray-500';
        },

        formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
        }
    }
}
</script>
@endpush
@endsection
