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
    <div class="mb-6 flex items-center justify-between">
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
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <span class="inline-flex h-12 w-12 items-center justify-center rounded-lg" :class="statusBgClass(stats.application?.status)">
                            <span class="h-3 w-3 rounded-full" :class="statusDotClass(stats.application?.status)"></span>
                        </span>
                        <div>
                            <h2 class="text-xl font-semibold text-white" x-text="stats.application?.name"></h2>
                            <div class="mt-1 flex items-center space-x-3 text-sm">
                                <span :class="statusClass(stats.application?.status)" x-text="stats.application?.status"></span>
                                <span class="text-gray-500">&bull;</span>
                                <span class="text-gray-400" x-text="stats.application?.build_pack"></span>
                                <span class="text-gray-500" x-show="stats.application?.fqdn">&bull;</span>
                                <a :href="stats.application?.fqdn" target="_blank" class="text-gray-400 hover:text-green-400" x-text="stats.application?.fqdn?.replace('https://', '').replace('http://', '')" x-show="stats.application?.fqdn"></a>
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
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="mb-6" x-show="stats.application || Object.keys(stats.databases).length > 0" x-cloak>
        <nav class="flex space-x-4">
            <button @click="activeTab = 'deployments'" x-show="stats.application" :class="activeTab === 'deployments' ? 'bg-gray-700 text-white' : 'text-gray-400 hover:text-white'" class="rounded-lg px-4 py-2 text-sm font-medium">Deployments</button>
            <button @click="activeTab = 'logs'; loadAppLogs()" x-show="stats.application" :class="activeTab === 'logs' ? 'bg-gray-700 text-white' : 'text-gray-400 hover:text-white'" class="rounded-lg px-4 py-2 text-sm font-medium">App Logs</button>
            <button @click="activeTab = 'resources'" x-show="Object.keys(stats.databases).length > 0" :class="activeTab === 'resources' ? 'bg-gray-700 text-white' : 'text-gray-400 hover:text-white'" class="rounded-lg px-4 py-2 text-sm font-medium">Resources</button>
            <button @click="activeTab = 'envs'; loadEnvs()" x-show="stats.application" :class="activeTab === 'envs' ? 'bg-gray-700 text-white' : 'text-gray-400 hover:text-white'" class="rounded-lg px-4 py-2 text-sm font-medium">Environment</button>
            <button @click="activeTab = 'settings'" x-show="stats.application" :class="activeTab === 'settings' ? 'bg-gray-700 text-white' : 'text-gray-400 hover:text-white'" class="rounded-lg px-4 py-2 text-sm font-medium">Settings</button>
        </nav>
    </div>

    <!-- Tab Content -->
    <div x-show="stats.application" x-cloak>
        <!-- Deployments Tab -->
        <div x-show="activeTab === 'deployments'" class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 space-y-6">
                <!-- Deployments List -->
                <div class="overflow-hidden rounded-lg bg-gray-800 shadow">
                    <div class="border-b border-gray-700 px-6 py-4">
                        <h3 class="text-lg font-medium text-white">Recent Deployments</h3>
                    </div>
                    <div x-show="stats.recentDeployments?.length > 0">
                        <ul class="divide-y divide-gray-700">
                            <template x-for="deployment in stats.recentDeployments" :key="deployment.uuid">
                                <li class="px-6 py-4 hover:bg-gray-700/50 cursor-pointer" @click="viewDeploymentLogs(deployment.uuid)">
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
                                                <div class="mt-0.5 text-xs text-gray-400">
                                                    <span x-text="formatDate(deployment.created_at)"></span>
                                                    <span x-show="deployment.commit_message" class="ml-2 text-gray-500" x-text="'- ' + (deployment.commit_message || '').substring(0, 50)"></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-3">
                                            <span class="text-sm text-gray-400" x-show="deployment.finished_at" x-text="formatDuration(deployment.created_at, deployment.finished_at)"></span>
                                            <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                                            </svg>
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

                <!-- Deployment Logs Panel -->
                <div class="overflow-hidden rounded-lg bg-gray-800 shadow" x-show="selectedDeployment" x-cloak>
                    <div class="border-b border-gray-700 px-6 py-4 flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-medium text-white">Deployment Logs</h3>
                            <p class="text-sm text-gray-400" x-text="'Commit: ' + (selectedDeployment?.commit || 'N/A')"></p>
                        </div>
                        <div class="flex items-center space-x-4">
                            <label class="flex items-center text-sm text-gray-400 cursor-pointer">
                                <input type="checkbox" x-model="showDebugLogs" class="rounded bg-gray-900 border-gray-600 text-green-600 mr-2">
                                Show build logs
                            </label>
                            <button @click="selectedDeployment = null" class="text-gray-400 hover:text-white">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="p-4 max-h-[600px] overflow-auto bg-gray-900">
                        <div x-show="loadingLogs" class="text-center py-8 text-gray-400">Loading logs...</div>
                        <pre x-show="!loadingLogs" class="text-xs text-gray-300 font-mono whitespace-pre-wrap" x-html="formatDeploymentLogs(deploymentLogs)"></pre>
                    </div>
                </div>
            </div>

            <!-- Right Sidebar -->
            <div class="space-y-6">
                <!-- Resources -->
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
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375"/>
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
                        <p class="text-sm text-gray-400 mb-3">Add this to your GitHub repo.</p>
                        <div class="relative">
                            <pre class="rounded bg-gray-900 p-3 text-xs text-gray-300 font-mono overflow-x-auto whitespace-pre-wrap break-all max-h-24" x-text="stats.deployKey?.public_key"></pre>
                            <button @click="copyToClipboard(stats.deployKey?.public_key)" class="absolute top-2 right-2 rounded bg-gray-700 p-1.5 text-gray-400 hover:bg-gray-600 hover:text-white">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184"/>
                                </svg>
                            </button>
                        </div>
                        <div class="mt-3 text-xs" x-show="stats.application?.repository">
                            <a :href="getGitHubKeysUrl(stats.application?.repository)" target="_blank" class="text-green-400 hover:text-green-300">
                                Add to <span x-text="getRepoDisplayName(stats.application?.repository)"></span> &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- App Logs Tab -->
        <div x-show="activeTab === 'logs'" class="overflow-hidden rounded-lg bg-gray-800 shadow">
            <div class="border-b border-gray-700 px-6 py-4 flex items-center justify-between">
                <h3 class="text-lg font-medium text-white">Application Logs</h3>
                <button @click="loadAppLogs()" class="text-sm text-gray-400 hover:text-white">Refresh</button>
            </div>
            <div class="p-4 bg-gray-900 max-h-[600px] overflow-auto">
                <div x-show="loadingAppLogs" class="text-center py-8 text-gray-400">Loading logs...</div>
                <div x-show="!loadingAppLogs && appLogsError" class="text-center py-8 text-yellow-400" x-text="appLogsError"></div>
                <pre x-show="!loadingAppLogs && !appLogsError" class="text-xs text-gray-300 font-mono whitespace-pre-wrap" x-text="appLogs"></pre>
            </div>
        </div>
    </div>

    <!-- Resources Tab (outside stats.application check since databases can exist independently) -->
    <div x-show="activeTab === 'resources'" x-cloak>
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <template x-for="(db, key) in stats.databases" :key="key">
                <div class="overflow-hidden rounded-lg bg-gray-800 shadow">
                    <div class="border-b border-gray-700 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <span class="flex h-10 w-10 items-center justify-center rounded-lg" :class="db.category === 'redis' ? 'bg-red-900/50' : 'bg-blue-900/50'">
                                    <svg x-show="db.category === 'redis'" class="h-5 w-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-16.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a4.5 4.5 0 01.9-2.7L5.737 5.1a3.375 3.375 0 012.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 01.9 2.7m0 0a3 3 0 01-3 3m0 3h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008zm-3 6h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008z"/>
                                    </svg>
                                    <svg x-show="db.category !== 'redis'" class="h-5 w-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375"/>
                                    </svg>
                                </span>
                                <div>
                                    <h3 class="text-lg font-medium text-white" x-text="db.name"></h3>
                                    <div class="flex items-center space-x-2 text-sm">
                                        <span class="text-gray-400" x-text="db.type"></span>
                                        <span class="text-gray-600">&bull;</span>
                                        <span :class="statusClass(db.status)" x-text="db.status"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex space-x-2">
                                <button @click="startDatabase(db.uuid)" x-show="db.status === 'exited' || db.status === 'stopped'" :disabled="dbActions[db.uuid]" class="inline-flex items-center rounded-md bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-500 disabled:opacity-50">
                                    <svg class="h-4 w-4" :class="{ 'animate-spin': dbActions[db.uuid] === 'starting' }" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z"/>
                                    </svg>
                                </button>
                                <button @click="stopDatabase(db.uuid)" x-show="db.status === 'running'" :disabled="dbActions[db.uuid]" class="inline-flex items-center rounded-md bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-500 disabled:opacity-50">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 7.5A2.25 2.25 0 017.5 5.25h9a2.25 2.25 0 012.25 2.25v9a2.25 2.25 0 01-2.25 2.25h-9a2.25 2.25 0 01-2.25-2.25v-9z"/>
                                    </svg>
                                </button>
                                <button @click="restartDatabase(db.uuid)" x-show="db.status === 'running'" :disabled="dbActions[db.uuid]" class="inline-flex items-center rounded-md bg-gray-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-500 disabled:opacity-50">
                                    <svg class="h-4 w-4" :class="{ 'animate-spin': dbActions[db.uuid] === 'restarting' }" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="px-6 py-4 space-y-4">
                        <div x-show="db.image">
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Image</dt>
                            <dd class="mt-1 font-mono text-sm text-gray-300" x-text="db.image"></dd>
                        </div>
                        <div x-show="db.internal_db_url">
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Internal URL</dt>
                            <dd class="mt-1 font-mono text-xs text-gray-300 break-all" x-text="db.internal_db_url"></dd>
                        </div>
                        <div x-show="db.is_public && db.public_port">
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Public Port</dt>
                            <dd class="mt-1 font-mono text-sm text-gray-300" x-text="db.public_port"></dd>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div x-show="db.limits_memory && db.limits_memory !== '0'">
                                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Memory Limit</dt>
                                <dd class="mt-1 text-sm text-gray-300" x-text="db.limits_memory"></dd>
                            </div>
                            <div x-show="db.limits_cpus && db.limits_cpus !== '0'">
                                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">CPU Limit</dt>
                                <dd class="mt-1 text-sm text-gray-300" x-text="db.limits_cpus"></dd>
                            </div>
                        </div>
                        <div x-show="db.postgres_db || db.postgres_user">
                            <div class="grid grid-cols-2 gap-4">
                                <div x-show="db.postgres_db">
                                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Database</dt>
                                    <dd class="mt-1 text-sm text-gray-300" x-text="db.postgres_db"></dd>
                                </div>
                                <div x-show="db.postgres_user">
                                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">User</dt>
                                    <dd class="mt-1 text-sm text-gray-300" x-text="db.postgres_user"></dd>
                                </div>
                            </div>
                        </div>
                        <div class="pt-2 border-t border-gray-700">
                            <div class="flex items-center justify-between text-xs text-gray-500">
                                <span>UUID: <span class="font-mono" x-text="db.uuid"></span></span>
                                <span x-show="db.started_at" x-text="'Started: ' + formatDate(db.started_at)"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <div x-show="stats.application" x-cloak>
        <!-- Environment Tab -->
        <div x-show="activeTab === 'envs'" class="overflow-hidden rounded-lg bg-gray-800 shadow">
            <div class="border-b border-gray-700 px-6 py-4 flex items-center justify-between">
                <h3 class="text-lg font-medium text-white">Environment Variables</h3>
                <button @click="showAddEnv = true" class="inline-flex items-center rounded-md bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-500">
                    <svg class="mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
                    Add Variable
                </button>
            </div>

            <!-- Add Env Form -->
            <div x-show="showAddEnv" x-cloak class="border-b border-gray-700 px-6 py-4 bg-gray-900/50">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <input type="text" x-model="newEnv.key" placeholder="KEY" class="rounded-md bg-gray-900 border-gray-700 text-white text-sm px-3 py-2 focus:ring-green-500 focus:border-green-500">
                    <input type="text" x-model="newEnv.value" placeholder="value" class="rounded-md bg-gray-900 border-gray-700 text-white text-sm px-3 py-2 focus:ring-green-500 focus:border-green-500">
                    <div class="flex items-center space-x-2">
                        <label class="flex items-center text-sm text-gray-400">
                            <input type="checkbox" x-model="newEnv.is_buildtime" class="rounded bg-gray-900 border-gray-600 text-green-600 mr-2">
                            Build
                        </label>
                        <label class="flex items-center text-sm text-gray-400">
                            <input type="checkbox" x-model="newEnv.is_runtime" class="rounded bg-gray-900 border-gray-600 text-green-600 mr-2" checked>
                            Runtime
                        </label>
                        <button @click="addEnv()" class="ml-auto rounded-md bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-500">Save</button>
                        <button @click="showAddEnv = false; newEnv = {key: '', value: '', is_buildtime: false, is_runtime: true}" class="rounded-md bg-gray-700 px-3 py-1.5 text-sm font-medium text-gray-300 hover:bg-gray-600">Cancel</button>
                    </div>
                </div>
            </div>

            <div x-show="loadingEnvs" class="px-6 py-8 text-center text-gray-400">Loading...</div>
            <div x-show="!loadingEnvs && envs.length === 0" class="px-6 py-8 text-center text-gray-400">No environment variables</div>
            <div x-show="!loadingEnvs && envs.length > 0" class="divide-y divide-gray-700">
                <template x-for="env in envs" :key="env.uuid">
                    <div class="px-6 py-3 flex items-center justify-between hover:bg-gray-700/30">
                        <div class="flex items-center space-x-4 flex-1 min-w-0">
                            <span class="font-mono text-sm text-green-400 font-medium" x-text="env.key"></span>
                            <span class="font-mono text-sm text-gray-400 truncate" x-text="env.value"></span>
                        </div>
                        <div class="flex items-center space-x-3 ml-4">
                            <span x-show="env.is_buildtime" class="text-xs px-2 py-0.5 rounded bg-blue-900/50 text-blue-400">build</span>
                            <span x-show="env.is_runtime" class="text-xs px-2 py-0.5 rounded bg-purple-900/50 text-purple-400">runtime</span>
                            <button @click="deleteEnv(env.uuid)" class="text-gray-500 hover:text-red-400">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Settings Tab -->
        <div x-show="activeTab === 'settings'" class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="overflow-hidden rounded-lg bg-gray-800 shadow">
                <div class="border-b border-gray-700 px-6 py-4">
                    <h3 class="text-lg font-medium text-white">Application Details</h3>
                </div>
                <div class="px-6 py-4 space-y-4">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Repository</dt>
                        <dd class="mt-1 text-sm text-white" x-text="stats.application?.repository"></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Branch</dt>
                        <dd class="mt-1 text-sm text-white" x-text="stats.application?.branch"></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Last Commit</dt>
                        <dd class="mt-1 font-mono text-sm text-white" x-text="stats.application?.full_commit"></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Build Pack</dt>
                        <dd class="mt-1 text-sm text-white" x-text="stats.application?.build_pack"></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Project</dt>
                        <dd class="mt-1 text-sm text-white" x-text="stats.application?.project_name"></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Environment</dt>
                        <dd class="mt-1 text-sm text-white" x-text="stats.application?.environment_name"></dd>
                    </div>
                </div>
            </div>
            <div class="overflow-hidden rounded-lg bg-gray-800 shadow">
                <div class="border-b border-gray-700 px-6 py-4">
                    <h3 class="text-lg font-medium text-white">UUIDs</h3>
                </div>
                <div class="px-6 py-4 space-y-4">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Application UUID</dt>
                        <dd class="mt-1 font-mono text-sm text-gray-400" x-text="stats.application?.uuid"></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Project UUID</dt>
                        <dd class="mt-1 font-mono text-sm text-gray-400" x-text="stats.application?.project_uuid"></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Environment UUID</dt>
                        <dd class="mt-1 font-mono text-sm text-gray-400" x-text="stats.application?.environment_uuid"></dd>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Empty State -->
    <div x-show="stats.connected && !stats.application && Object.keys(stats.databases).length === 0" x-cloak class="text-center py-12">
        <svg class="mx-auto h-12 w-12 text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375"/>
        </svg>
        <h3 class="mt-4 text-lg font-medium text-white">No resources configured</h3>
        <p class="mt-2 text-sm text-gray-400">
            Set <code class="rounded bg-gray-700 px-1.5 py-0.5">COOLIFY_APPLICATION_UUID</code> in your .env to get started.
        </p>
    </div>
</div>

@push('scripts')
<script>
function dashboard() {
    return {
        stats: { connected: false, application: null, databases: {}, recentDeployments: [], deployKey: null },
        activeTab: 'deployments',
        deploying: false,
        restarting: false,
        pollInterval: {{ config('coolify.polling_interval', 10) * 1000 }},

        // Deployment logs
        selectedDeployment: null,
        deploymentLogs: [],
        loadingLogs: false,
        showDebugLogs: true, // Show build/debug logs by default

        // App logs
        appLogs: '',
        appLogsError: null,
        loadingAppLogs: false,

        // Environment variables
        envs: [],
        loadingEnvs: false,
        showAddEnv: false,
        newEnv: { key: '', value: '', is_buildtime: false, is_runtime: true },

        // Database actions
        dbActions: {},

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
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Content-Type': 'application/json' }
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
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Content-Type': 'application/json' }
                });
                await this.fetchStats();
            } catch (error) {
                console.error('Restart failed:', error);
            } finally {
                this.restarting = false;
            }
        },

        async viewDeploymentLogs(uuid) {
            this.selectedDeployment = this.stats.recentDeployments.find(d => d.uuid === uuid);
            this.loadingLogs = true;
            this.deploymentLogs = [];
            try {
                const response = await fetch(`{{ url(config('coolify.path')) }}/api/deployments/${uuid}/logs`);
                this.deploymentLogs = await response.json();
            } catch (error) {
                console.error('Failed to fetch logs:', error);
            } finally {
                this.loadingLogs = false;
            }
        },

        formatDeploymentLogs(logs) {
            if (!logs || !logs.length) return 'No logs available';
            return logs
                .filter(l => this.showDebugLogs || !l.hidden)
                .map(l => {
                    const time = l.timestamp ? new Date(l.timestamp).toLocaleTimeString() : '';
                    const output = l.output || '';
                    const lowerOutput = output.toLowerCase();

                    // Determine color based on content, not just stderr
                    let color = 'text-gray-300';
                    if (lowerOutput.includes('error') || lowerOutput.includes('failed') || lowerOutput.includes('fatal')) {
                        color = 'text-red-400';
                    } else if (l.type === 'stderr' || lowerOutput.includes('warning') || lowerOutput.includes('warn')) {
                        color = 'text-yellow-400';
                    } else if (lowerOutput.includes('success') || lowerOutput.includes('completed') || lowerOutput.includes('done')) {
                        color = 'text-green-400';
                    }

                    const hiddenBadge = l.hidden ? '<span class="text-blue-400">[build]</span> ' : '';
                    return `<span class="text-gray-500">[${time}]</span> ${hiddenBadge}<span class="${color}">${this.escapeHtml(output)}</span>`;
                })
                .join('\n');
        },

        async loadAppLogs() {
            if (!this.stats.application?.uuid) return;
            this.loadingAppLogs = true;
            this.appLogsError = null;
            try {
                const response = await fetch(`{{ url(config('coolify.path')) }}/api/applications/${this.stats.application.uuid}/logs?lines=200`);
                const data = await response.json();
                if (data.error) {
                    this.appLogsError = data.error;
                } else if (data.message) {
                    this.appLogsError = data.message;
                } else {
                    this.appLogs = Array.isArray(data) ? data.join('\n') : (data.logs || JSON.stringify(data, null, 2));
                }
            } catch (error) {
                this.appLogsError = 'Failed to load logs';
            } finally {
                this.loadingAppLogs = false;
            }
        },

        async loadEnvs() {
            if (!this.stats.application?.uuid) return;
            this.loadingEnvs = true;
            try {
                const response = await fetch(`{{ url(config('coolify.path')) }}/api/applications/${this.stats.application.uuid}/envs`);
                this.envs = await response.json();
            } catch (error) {
                console.error('Failed to fetch envs:', error);
            } finally {
                this.loadingEnvs = false;
            }
        },

        async addEnv() {
            if (!this.newEnv.key || !this.stats.application?.uuid) return;
            try {
                await fetch(`{{ url(config('coolify.path')) }}/api/applications/${this.stats.application.uuid}/envs`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.newEnv)
                });
                this.showAddEnv = false;
                this.newEnv = { key: '', value: '', is_buildtime: false, is_runtime: true };
                await this.loadEnvs();
            } catch (error) {
                console.error('Failed to add env:', error);
            }
        },

        async deleteEnv(envUuid) {
            if (!confirm('Delete this environment variable?')) return;
            try {
                await fetch(`{{ url(config('coolify.path')) }}/api/applications/${this.stats.application.uuid}/envs/${envUuid}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                });
                await this.loadEnvs();
            } catch (error) {
                console.error('Failed to delete env:', error);
            }
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        async startDatabase(uuid) {
            if (this.dbActions[uuid]) return;
            this.dbActions[uuid] = 'starting';
            try {
                await fetch(`{{ url(config('coolify.path')) }}/api/databases/${uuid}/start`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Content-Type': 'application/json' }
                });
                await this.fetchStats();
            } catch (error) {
                console.error('Start database failed:', error);
            } finally {
                this.dbActions[uuid] = null;
            }
        },

        async stopDatabase(uuid) {
            if (this.dbActions[uuid]) return;
            if (!confirm('Stop this database? This will disconnect any active connections.')) return;
            this.dbActions[uuid] = 'stopping';
            try {
                await fetch(`{{ url(config('coolify.path')) }}/api/databases/${uuid}/stop`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Content-Type': 'application/json' }
                });
                await this.fetchStats();
            } catch (error) {
                console.error('Stop database failed:', error);
            } finally {
                this.dbActions[uuid] = null;
            }
        },

        async restartDatabase(uuid) {
            if (this.dbActions[uuid]) return;
            this.dbActions[uuid] = 'restarting';
            try {
                await fetch(`{{ url(config('coolify.path')) }}/api/databases/${uuid}/restart`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Content-Type': 'application/json' }
                });
                await this.fetchStats();
            } catch (error) {
                console.error('Restart database failed:', error);
            } finally {
                this.dbActions[uuid] = null;
            }
        },

        statusClass(status) {
            const s = (status || '').toLowerCase();
            if (s === 'running' || s === 'finished' || s === 'success') return 'text-green-400';
            if (s === 'stopped' || s === 'exited' || s === 'failed' || s === 'error') return 'text-red-400';
            if (s === 'deploying' || s === 'starting' || s === 'restarting' || s === 'in_progress' || s === 'queued') return 'text-blue-400';
            return 'text-gray-400';
        },
        statusBgClass(status) {
            const s = (status || '').toLowerCase();
            if (s === 'running' || s === 'finished' || s === 'success') return 'bg-green-900/30';
            if (s === 'stopped' || s === 'exited' || s === 'failed' || s === 'error') return 'bg-red-900/30';
            if (s === 'deploying' || s === 'starting' || s === 'restarting' || s === 'in_progress' || s === 'queued') return 'bg-blue-900/30';
            return 'bg-gray-900/30';
        },
        statusDotClass(status) {
            const s = (status || '').toLowerCase();
            if (s === 'running' || s === 'finished' || s === 'success') return 'bg-green-500';
            if (s === 'stopped' || s === 'exited' || s === 'failed' || s === 'error') return 'bg-red-500';
            if (s === 'deploying' || s === 'starting' || s === 'restarting' || s === 'in_progress' || s === 'queued') return 'bg-blue-500 animate-pulse';
            return 'bg-gray-500';
        },
        statusBadgeClass(status) {
            const s = (status || '').toLowerCase();
            if (s === 'running' || s === 'finished' || s === 'success') return 'bg-green-900/50 text-green-400';
            if (s === 'stopped' || s === 'exited' || s === 'failed' || s === 'error') return 'bg-red-900/50 text-red-400';
            if (s === 'deploying' || s === 'starting' || s === 'restarting' || s === 'in_progress' || s === 'queued') return 'bg-blue-900/50 text-blue-400';
            return 'bg-gray-900/50 text-gray-400';
        },
        formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            const now = new Date();
            const diff = now - date;
            if (diff < 60000) return 'Just now';
            if (diff < 3600000) return Math.floor(diff / 60000) + 'm ago';
            if (diff < 86400000) return Math.floor(diff / 3600000) + 'h ago';
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
        copyToClipboard(text) {
            if (!text) return;
            // Fallback for non-HTTPS contexts where navigator.clipboard is undefined
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).catch(err => console.error('Failed to copy:', err));
            } else {
                // Fallback using textarea
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                try { document.execCommand('copy'); } catch (err) { console.error('Failed to copy:', err); }
                document.body.removeChild(textarea);
            }
        },
        getRepoDisplayName(repo) {
            if (!repo) return '';
            if (repo.startsWith('git@')) return repo.replace(/^git@github\.com:/, '').replace(/\.git$/, '');
            if (repo.includes('github.com/')) return repo.replace(/^https?:\/\/github\.com\//, '').replace(/\.git$/, '');
            return repo;
        },
        getGitHubKeysUrl(repo) {
            const repoName = this.getRepoDisplayName(repo);
            return repoName ? `https://github.com/${repoName}/settings/keys` : '#';
        }
    }
}
</script>
@endpush
@endsection
