@extends('coolify::layout')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8" x-data="dashboard()" x-init="init()">
    <!-- Status Banner -->
    <div x-show="!stats.connected" x-cloak class="mb-6 rounded-lg bg-yellow-900/20 p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-yellow-200">Not Connected</h3>
                <p class="mt-1 text-sm text-yellow-300">
                    Unable to connect to Coolify. Check your <code class="rounded bg-yellow-800 px-1">COOLIFY_URL</code> and <code class="rounded bg-yellow-800 px-1">COOLIFY_TOKEN</code> configuration.
                </p>
            </div>
        </div>
    </div>

    <!-- Header -->
    <div class="mb-8 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white">Infrastructure Dashboard</h1>
            <p class="mt-1 text-sm text-gray-400">Monitor and manage your Coolify resources</p>
        </div>
        <div x-show="stats.application?.coolify_url" x-cloak>
            <a :href="stats.application?.coolify_url" target="_blank" class="inline-flex items-center rounded-md bg-gray-700 px-3 py-2 text-sm font-medium text-gray-300 hover:bg-gray-600 hover:text-white">
                Open in Coolify
                <svg class="ml-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                </svg>
            </a>
        </div>
    </div>

    <!-- Application Card -->
    <div class="mb-6" x-show="stats.application" x-cloak>
        <div class="overflow-hidden rounded-lg bg-gray-800 shadow">
            <div class="px-6 py-5">
                <!-- App Header -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <div class="flex-shrink-0">
                            <span class="inline-flex h-12 w-12 items-center justify-center rounded-lg" :class="statusBgClass(stats.application?.status)">
                                <span class="h-3 w-3 rounded-full" :class="statusDotClass(stats.application?.status)"></span>
                            </span>
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold text-white" x-text="stats.application?.name"></h2>
                            <div class="mt-1 flex items-center space-x-3 text-sm">
                                <span :class="statusClass(stats.application?.status)" x-text="stats.application?.status"></span>
                                <span class="text-gray-500">&bull;</span>
                                <span class="text-gray-400" x-text="stats.application?.build_pack"></span>
                            </div>
                        </div>
                    </div>
                    <div class="flex space-x-2">
                        <button @click="deployApp()" :disabled="deploying" class="inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-500 disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg class="mr-1.5 h-4 w-4" :class="{ 'animate-spin': deploying }" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/>
                            </svg>
                            <span x-text="deploying ? 'Deploying...' : 'Deploy'"></span>
                        </button>
                        <button @click="restartApp()" :disabled="restarting" class="inline-flex items-center rounded-md bg-gray-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-500 disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg class="mr-1.5 h-4 w-4" :class="{ 'animate-spin': restarting }" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.636 5.636a9 9 0 1012.728 0M12 3v9"/>
                            </svg>
                            <span x-text="restarting ? 'Restarting...' : 'Restart'"></span>
                        </button>
                    </div>
                </div>

                <!-- App Details Grid -->
                <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div class="rounded-lg bg-gray-900/50 p-4">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Domain</dt>
                        <dd class="mt-1 text-sm text-white truncate">
                            <a :href="stats.application?.fqdn" target="_blank" class="hover:text-green-400" x-text="stats.application?.fqdn?.replace('https://', '').replace('http://', '')"></a>
                        </dd>
                    </div>
                    <div class="rounded-lg bg-gray-900/50 p-4">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Repository</dt>
                        <dd class="mt-1 text-sm text-white truncate" x-text="stats.application?.repository"></dd>
                    </div>
                    <div class="rounded-lg bg-gray-900/50 p-4">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Branch</dt>
                        <dd class="mt-1 text-sm text-white" x-text="stats.application?.branch"></dd>
                    </div>
                    <div class="rounded-lg bg-gray-900/50 p-4">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Last Commit</dt>
                        <dd class="mt-1 font-mono text-sm text-white" x-text="stats.application?.commit"></dd>
                    </div>
                </div>

                <!-- Project/Environment Info -->
                <div class="mt-4 flex items-center space-x-4 text-sm text-gray-400" x-show="stats.application?.project_name || stats.application?.environment_name">
                    <span x-show="stats.application?.project_name">
                        <span class="text-gray-500">Project:</span> <span class="text-gray-300" x-text="stats.application?.project_name"></span>
                    </span>
                    <span x-show="stats.application?.environment_name">
                        <span class="text-gray-500">Environment:</span> <span class="text-gray-300" x-text="stats.application?.environment_name"></span>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Two Column Layout -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Left Column - Deployments -->
        <div class="lg:col-span-2">
            <!-- Recent Deployments -->
            <div class="overflow-hidden rounded-lg bg-gray-800 shadow">
                <div class="border-b border-gray-700 px-6 py-4">
                    <h3 class="text-lg font-medium text-white">Recent Deployments</h3>
                </div>
                <div x-show="stats.recentDeployments?.length > 0">
                    <ul class="divide-y divide-gray-700">
                        <template x-for="deployment in stats.recentDeployments" :key="deployment.uuid">
                            <li class="px-6 py-4 hover:bg-gray-700/50">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-4">
                                        <span class="flex h-8 w-8 items-center justify-center rounded-full" :class="statusBgClass(deployment.status)">
                                            <span class="h-2 w-2 rounded-full" :class="statusDotClass(deployment.status)"></span>
                                        </span>
                                        <div>
                                            <div class="flex items-center space-x-2">
                                                <span class="font-mono text-sm text-white" x-text="deployment.commit || 'No commit'"></span>
                                                <span class="text-xs px-2 py-0.5 rounded" :class="statusBadgeClass(deployment.status)" x-text="deployment.status"></span>
                                            </div>
                                            <div class="mt-0.5 text-xs text-gray-400" x-text="formatDate(deployment.created_at)"></div>
                                        </div>
                                    </div>
                                    <div class="text-right text-sm text-gray-400" x-show="deployment.finished_at">
                                        <span x-text="formatDuration(deployment.created_at, deployment.finished_at)"></span>
                                    </div>
                                </div>
                            </li>
                        </template>
                    </ul>
                </div>
                <div x-show="!stats.recentDeployments?.length" class="px-6 py-8 text-center text-gray-400">
                    No deployments yet
                </div>
            </div>
        </div>

        <!-- Right Column - Resources & Config -->
        <div class="space-y-6">
            <!-- Databases -->
            <div class="overflow-hidden rounded-lg bg-gray-800 shadow" x-show="Object.keys(stats.databases).length > 0" x-cloak>
                <div class="border-b border-gray-700 px-6 py-4">
                    <h3 class="text-lg font-medium text-white">Resources</h3>
                </div>
                <div class="divide-y divide-gray-700">
                    <template x-for="(db, key) in stats.databases" :key="key">
                        <div class="px-6 py-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-900/50">
                                        <svg class="h-4 w-4 text-blue-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125"/>
                                        </svg>
                                    </span>
                                    <div>
                                        <div class="text-sm font-medium text-white" x-text="db.name"></div>
                                        <div class="text-xs text-gray-400" x-text="db.type"></div>
                                    </div>
                                </div>
                                <span class="text-xs px-2 py-0.5 rounded" :class="statusBadgeClass(db.status)" x-text="db.status"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Deploy Key -->
            <div class="overflow-hidden rounded-lg bg-gray-800 shadow" x-show="stats.deployKey" x-cloak>
                <div class="border-b border-gray-700 px-6 py-4">
                    <h3 class="text-lg font-medium text-white">Deploy Key</h3>
                </div>
                <div class="px-6 py-4">
                    <p class="text-sm text-gray-400 mb-3">Add this public key to your GitHub repository as a deploy key.</p>
                    <div class="relative">
                        <pre class="rounded bg-gray-900 p-3 text-xs text-gray-300 font-mono overflow-x-auto whitespace-pre-wrap break-all" x-text="stats.deployKey?.public_key"></pre>
                        <button @click="copyToClipboard(stats.deployKey?.public_key)" class="absolute top-2 right-2 rounded bg-gray-700 p-1.5 text-gray-400 hover:bg-gray-600 hover:text-white">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184"/>
                            </svg>
                        </button>
                    </div>
                    <div class="mt-3 text-xs text-gray-500" x-show="stats.application?.repository">
                        <a :href="getGitHubKeysUrl(stats.application?.repository)" target="_blank" class="text-green-400 hover:text-green-300">
                            Add to <span x-text="getRepoDisplayName(stats.application?.repository)"></span> &rarr;
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Empty State -->
    <div x-show="stats.connected && !stats.application && Object.keys(stats.databases).length === 0" x-cloak class="text-center py-12">
        <svg class="mx-auto h-12 w-12 text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125"/>
        </svg>
        <h3 class="mt-4 text-lg font-medium text-white">No resources configured</h3>
        <p class="mt-2 text-sm text-gray-400">
            Set <code class="rounded bg-gray-700 px-1.5 py-0.5">COOLIFY_APPLICATION_UUID</code> in your .env to get started.
        </p>
        <div class="mt-6">
            <a href="https://coolify.io/docs" target="_blank" class="text-sm font-medium text-green-400 hover:text-green-300">
                View documentation &rarr;
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
            recentDeployments: [],
            deployKey: null
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
            if (s === 'running' || s === 'finished' || s === 'success') return 'text-green-400';
            if (s === 'stopped' || s === 'failed' || s === 'error') return 'text-red-400';
            if (s === 'deploying' || s === 'starting' || s === 'restarting' || s === 'in_progress' || s === 'queued') return 'text-blue-400';
            return 'text-gray-400';
        },

        statusBgClass(status) {
            const s = (status || '').toLowerCase();
            if (s === 'running' || s === 'finished' || s === 'success') return 'bg-green-900/30';
            if (s === 'stopped' || s === 'failed' || s === 'error') return 'bg-red-900/30';
            if (s === 'deploying' || s === 'starting' || s === 'restarting' || s === 'in_progress' || s === 'queued') return 'bg-blue-900/30';
            return 'bg-gray-900/30';
        },

        statusDotClass(status) {
            const s = (status || '').toLowerCase();
            if (s === 'running' || s === 'finished' || s === 'success') return 'bg-green-500';
            if (s === 'stopped' || s === 'failed' || s === 'error') return 'bg-red-500';
            if (s === 'deploying' || s === 'starting' || s === 'restarting' || s === 'in_progress' || s === 'queued') return 'bg-blue-500 animate-pulse';
            return 'bg-gray-500';
        },

        statusBadgeClass(status) {
            const s = (status || '').toLowerCase();
            if (s === 'running' || s === 'finished' || s === 'success') return 'bg-green-900/50 text-green-400';
            if (s === 'stopped' || s === 'failed' || s === 'error') return 'bg-red-900/50 text-red-400';
            if (s === 'deploying' || s === 'starting' || s === 'restarting' || s === 'in_progress' || s === 'queued') return 'bg-blue-900/50 text-blue-400';
            return 'bg-gray-900/50 text-gray-400';
        },

        formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            const now = new Date();
            const diff = now - date;

            // Less than a minute
            if (diff < 60000) return 'Just now';
            // Less than an hour
            if (diff < 3600000) return Math.floor(diff / 60000) + 'm ago';
            // Less than a day
            if (diff < 86400000) return Math.floor(diff / 3600000) + 'h ago';
            // Less than a week
            if (diff < 604800000) return Math.floor(diff / 86400000) + 'd ago';

            return date.toLocaleDateString();
        },

        formatDuration(start, end) {
            if (!start || !end) return '';
            const diff = new Date(end) - new Date(start);
            const seconds = Math.floor(diff / 1000);
            if (seconds < 60) return seconds + 's';
            const minutes = Math.floor(seconds / 60);
            const remainingSeconds = seconds % 60;
            return minutes + 'm ' + remainingSeconds + 's';
        },

        async copyToClipboard(text) {
            if (!text) return;
            try {
                await navigator.clipboard.writeText(text);
            } catch (err) {
                console.error('Failed to copy:', err);
            }
        },

        // Parse git repository URL (handles both SSH and HTTPS formats)
        // git@github.com:StuMason/test-system.git -> StuMason/test-system
        // https://github.com/StuMason/test-system.git -> StuMason/test-system
        getRepoDisplayName(repo) {
            if (!repo) return '';
            // SSH format: git@github.com:user/repo.git
            if (repo.startsWith('git@')) {
                return repo.replace(/^git@github\.com:/, '').replace(/\.git$/, '');
            }
            // HTTPS format: https://github.com/user/repo.git
            if (repo.includes('github.com/')) {
                return repo.replace(/^https?:\/\/github\.com\//, '').replace(/\.git$/, '');
            }
            return repo;
        },

        getGitHubKeysUrl(repo) {
            const repoName = this.getRepoDisplayName(repo);
            if (!repoName) return '#';
            return `https://github.com/${repoName}/settings/keys`;
        }
    }
}
</script>
@endpush
@endsection
