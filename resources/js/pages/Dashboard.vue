<script setup>
import { inject, computed, ref } from 'vue';
import { RouterLink } from 'vue-router';

const stats = inject('stats');
const api = inject('api');
const refreshStats = inject('refreshStats');
const toast = inject('toast');

const deploying = ref(false);
const restarting = ref(false);
const appAction = ref(null);
const dbAction = ref(null);
const cacheAction = ref(null);

const app = computed(() => stats.value?.application || {});
const deployments = computed(() => stats.value?.recentDeployments || []);
const database = computed(() => stats.value?.databases?.primary);
const cache = computed(() => stats.value?.databases?.redis);
const connected = computed(() => stats.value?.connected);

// Coolify URLs
const coolifyUrl = computed(() => {
    const baseUrl = window.Coolify?.coolifyUrl || stats.value?.coolify_url;
    if (!baseUrl) return null;
    return baseUrl.replace(/\/$/, '');
});

const appCoolifyUrl = computed(() => {
    if (!coolifyUrl.value || !app.value?.uuid) return null;
    const projectUuid = stats.value?.project?.uuid;
    const envName = stats.value?.environment?.name || 'production';
    if (projectUuid) {
        return `${coolifyUrl.value}/project/${projectUuid}/${envName}/application/${app.value.uuid}`;
    }
    return `${coolifyUrl.value}/application/${app.value.uuid}`;
});

const dbCoolifyUrl = computed(() => {
    if (!coolifyUrl.value || !database.value?.uuid) return null;
    const projectUuid = stats.value?.project?.uuid;
    const envName = stats.value?.environment?.name || 'production';
    if (projectUuid) {
        return `${coolifyUrl.value}/project/${projectUuid}/${envName}/database/${database.value.uuid}`;
    }
    return `${coolifyUrl.value}/database/${database.value.uuid}`;
});

const cacheCoolifyUrl = computed(() => {
    if (!coolifyUrl.value || !cache.value?.uuid) return null;
    const projectUuid = stats.value?.project?.uuid;
    const envName = stats.value?.environment?.name || 'production';
    if (projectUuid) {
        return `${coolifyUrl.value}/project/${projectUuid}/${envName}/database/${cache.value.uuid}`;
    }
    return `${coolifyUrl.value}/database/${cache.value.uuid}`;
});

// Status helpers
function isRunning(status) {
    return ['running', 'running:healthy', 'healthy'].includes(status?.toLowerCase());
}

function isStopped(status) {
    return ['stopped', 'exited', 'error', 'failed', 'exited:unhealthy'].includes(status?.toLowerCase());
}

function isTransitioning(status) {
    return ['starting', 'stopping', 'restarting', 'building'].includes(status?.toLowerCase());
}

function statusClass(status) {
    const s = status?.toLowerCase();
    if (s === 'running' || s === 'running:healthy' || s === 'healthy' || s === 'finished') {
        return 'bg-emerald-500/10 text-emerald-400';
    }
    if (s === 'running:unhealthy' || s === 'unhealthy') {
        return 'bg-amber-500/10 text-amber-400';
    }
    if (s === 'stopped' || s === 'exited' || s === 'error' || s === 'failed' || s === 'exited:unhealthy') {
        return 'bg-red-500/10 text-red-400';
    }
    if (s === 'starting' || s === 'stopping' || s === 'restarting' || s === 'building' || s === 'in_progress' || s === 'queued') {
        return 'bg-blue-500/10 text-blue-400';
    }
    return 'bg-zinc-500/10 text-zinc-400';
}

function statusColor(status) {
    const s = status?.toLowerCase();
    if (s === 'running' || s === 'running:healthy' || s === 'healthy') return 'text-emerald-400';
    if (s === 'running:unhealthy' || s === 'unhealthy') return 'text-amber-400';
    if (s === 'stopped' || s === 'exited' || s === 'error' || s === 'failed' || s === 'exited:unhealthy') return 'text-red-400';
    if (s === 'starting' || s === 'stopping' || s === 'restarting' || s === 'building') return 'text-blue-400';
    return 'text-zinc-400';
}

function formatStatus(status) {
    if (!status) return 'Unknown';
    return status.replace('running:', '').replace('exited:', '').replace('_', ' ');
}

// App actions
async function deploy() {
    if (deploying.value) return;
    deploying.value = true;
    try {
        await api.deployApplication(app.value.uuid);
        toast.value?.success('Deployment Started', 'A new deployment has been triggered');
        await refreshStats();
    } catch (e) {
        toast.value?.error('Deployment Failed', e.message);
    } finally {
        deploying.value = false;
    }
}

async function appControl(action) {
    if (appAction.value) return;
    appAction.value = action;
    try {
        if (action === 'restart') await api.restartApplication(app.value.uuid);
        else if (action === 'stop') await api.stopApplication(app.value.uuid);
        else if (action === 'start') await api.startApplication(app.value.uuid);
        toast.value?.success(`Application ${action === 'restart' ? 'Restarting' : action === 'stop' ? 'Stopping' : 'Starting'}`, `The application is ${action}ing`);
        await refreshStats();
    } catch (e) {
        toast.value?.error(`Failed to ${action}`, e.message);
    } finally {
        appAction.value = null;
    }
}

// Database actions
async function dbControl(action) {
    if (dbAction.value || !database.value) return;
    dbAction.value = action;
    try {
        if (action === 'start') await api.startDatabase(database.value.uuid);
        else if (action === 'stop') await api.stopDatabase(database.value.uuid);
        else if (action === 'restart') await api.restartDatabase(database.value.uuid);
        toast.value?.success(`Database ${action === 'restart' ? 'Restarting' : action === 'stop' ? 'Stopping' : 'Starting'}`, `${database.value.name} is ${action}ing`);
        await refreshStats();
    } catch (e) {
        toast.value?.error(`Database ${action} Failed`, e.message);
    } finally {
        dbAction.value = null;
    }
}

// Cache actions
async function cacheControl(action) {
    if (cacheAction.value || !cache.value) return;
    cacheAction.value = action;
    try {
        if (action === 'start') await api.startDatabase(cache.value.uuid);
        else if (action === 'stop') await api.stopDatabase(cache.value.uuid);
        else if (action === 'restart') await api.restartDatabase(cache.value.uuid);
        toast.value?.success(`Cache ${action === 'restart' ? 'Restarting' : action === 'stop' ? 'Stopping' : 'Starting'}`, `${cache.value.name} is ${action}ing`);
        await refreshStats();
    } catch (e) {
        toast.value?.error(`Cache ${action} Failed`, e.message);
    } finally {
        cacheAction.value = null;
    }
}

function formatDate(date) {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatDuration(seconds) {
    if (!seconds) return '-';
    if (seconds < 60) return `${seconds}s`;
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}m ${secs}s`;
}
</script>

<template>
    <div class="p-6 space-y-6">
        <!-- Not connected -->
        <div v-if="!connected" class="rounded-xl border border-red-500/20 bg-red-500/5 p-6 text-center">
            <svg class="mx-auto h-12 w-12 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
            </svg>
            <h3 class="mt-4 text-lg font-medium text-white">Not Connected</h3>
            <p class="mt-2 text-sm text-zinc-400">Unable to connect to Coolify. Check your configuration.</p>
        </div>

        <template v-else>
            <!-- Header with actions -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-white">{{ app.name || 'Dashboard' }}</h1>
                    <div class="mt-1 flex items-center gap-3">
                        <a
                            v-if="app.fqdn"
                            :href="app.fqdn"
                            target="_blank"
                            class="flex items-center gap-1.5 text-sm text-zinc-400 hover:text-white transition-colors"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5a17.92 17.92 0 0 1-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" />
                            </svg>
                            {{ app.fqdn.replace(/^https?:\/\//, '') }}
                        </a>
                        <a
                            v-if="appCoolifyUrl"
                            :href="appCoolifyUrl"
                            target="_blank"
                            class="flex items-center gap-1.5 text-sm text-violet-400 hover:text-violet-300 transition-colors"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                            </svg>
                            Coolify
                        </a>
                        <a
                            v-if="app.repository"
                            :href="`https://github.com/${app.repository}`"
                            target="_blank"
                            class="flex items-center gap-1.5 text-sm text-zinc-400 hover:text-white transition-colors"
                        >
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                            </svg>
                            GitHub
                        </a>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <!-- Stop/Start based on status -->
                    <button
                        v-if="isRunning(app.status)"
                        @click="appControl('stop')"
                        :disabled="appAction"
                        class="inline-flex items-center gap-2 rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-sm font-medium text-white hover:bg-zinc-700 disabled:opacity-50 transition-colors"
                    >
                        <span v-if="appAction === 'stop'" class="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                        <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 7.5A2.25 2.25 0 0 1 7.5 5.25h9a2.25 2.25 0 0 1 2.25 2.25v9a2.25 2.25 0 0 1-2.25 2.25h-9a2.25 2.25 0 0 1-2.25-2.25v-9Z" />
                        </svg>
                        Stop
                    </button>
                    <button
                        v-if="isStopped(app.status)"
                        @click="appControl('start')"
                        :disabled="appAction"
                        class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-500 disabled:opacity-50 transition-colors"
                    >
                        <span v-if="appAction === 'start'" class="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                        <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                        </svg>
                        Start
                    </button>
                    <!-- Restart -->
                    <button
                        v-if="isRunning(app.status)"
                        @click="appControl('restart')"
                        :disabled="appAction"
                        class="inline-flex items-center gap-2 rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-sm font-medium text-white hover:bg-zinc-700 disabled:opacity-50 transition-colors"
                    >
                        <span v-if="appAction === 'restart'" class="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                        <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        Restart
                    </button>
                    <!-- Deploy -->
                    <button
                        @click="deploy"
                        :disabled="deploying || isTransitioning(app.status)"
                        class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-500 disabled:opacity-50 transition-colors"
                    >
                        <span v-if="deploying" class="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                        <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 0 1-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 0 0 6.16-12.12A14.98 14.98 0 0 0 9.631 8.41m5.96 5.96a14.926 14.926 0 0 1-5.841 2.58m-.119-8.54a6 6 0 0 0-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 0 0-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 0 1-2.448-2.448 14.9 14.9 0 0 1 .06-.312m-2.24 2.39a4.493 4.493 0 0 0-1.757 4.306 4.493 4.493 0 0 0 4.306-1.758M16.5 9a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" />
                        </svg>
                        Deploy
                    </button>
                </div>
            </div>

            <!-- Status Cards -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
                    <div class="text-sm font-medium text-zinc-400">Status</div>
                    <div class="mt-2">
                        <span :class="[statusColor(app.status), 'text-2xl font-semibold capitalize']">{{ formatStatus(app.status) }}</span>
                    </div>
                </div>
                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
                    <div class="text-sm font-medium text-zinc-400">Build Pack</div>
                    <div class="mt-2 text-2xl font-semibold text-white capitalize">{{ app.build_pack || '-' }}</div>
                </div>
                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
                    <div class="text-sm font-medium text-zinc-400">Branch</div>
                    <div class="mt-2 text-2xl font-semibold text-white">{{ app.branch || '-' }}</div>
                </div>
                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
                    <div class="text-sm font-medium text-zinc-400">Last Deploy</div>
                    <div class="mt-2 text-lg font-semibold text-white">{{ formatDate(deployments[0]?.created_at) }}</div>
                </div>
            </div>

            <!-- Resources -->
            <div class="grid gap-4 lg:grid-cols-2" v-if="database || cache">
                <!-- Database -->
                <div v-if="database" class="rounded-xl border border-zinc-800 bg-zinc-900">
                    <div class="border-b border-zinc-800 px-5 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-500/10">
                                <svg class="h-5 w-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                                </svg>
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-white">{{ database.name }}</span>
                                    <a
                                        v-if="dbCoolifyUrl"
                                        :href="dbCoolifyUrl"
                                        target="_blank"
                                        class="text-violet-400 hover:text-violet-300 transition-colors"
                                        title="Open in Coolify"
                                    >
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                        </svg>
                                    </a>
                                </div>
                                <div class="text-sm text-zinc-400">{{ database.type }}</div>
                            </div>
                        </div>
                        <span :class="[statusClass(database.status), 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize']">
                            {{ formatStatus(database.status) }}
                        </span>
                    </div>
                    <div class="px-5 py-4 flex items-center gap-2">
                        <button
                            v-if="isStopped(database.status)"
                            @click="dbControl('start')"
                            :disabled="dbAction"
                            class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-500 disabled:opacity-50 transition-colors"
                        >
                            <span v-if="dbAction === 'start'" class="h-3 w-3 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                            Start
                        </button>
                        <button
                            v-if="isRunning(database.status)"
                            @click="dbControl('stop')"
                            :disabled="dbAction"
                            class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-500 disabled:opacity-50 transition-colors"
                        >
                            <span v-if="dbAction === 'stop'" class="h-3 w-3 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                            Stop
                        </button>
                        <button
                            v-if="isRunning(database.status)"
                            @click="dbControl('restart')"
                            :disabled="dbAction"
                            class="inline-flex items-center gap-2 rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-zinc-700 disabled:opacity-50 transition-colors"
                        >
                            <span v-if="dbAction === 'restart'" class="h-3 w-3 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                            Restart
                        </button>
                        <span v-if="isTransitioning(database.status)" class="text-sm text-zinc-400 italic">{{ database.status }}...</span>
                    </div>
                </div>

                <!-- Cache -->
                <div v-if="cache" class="rounded-xl border border-zinc-800 bg-zinc-900">
                    <div class="border-b border-zinc-800 px-5 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-500/10">
                                <svg class="h-5 w-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                                </svg>
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-white">{{ cache.name }}</span>
                                    <a
                                        v-if="cacheCoolifyUrl"
                                        :href="cacheCoolifyUrl"
                                        target="_blank"
                                        class="text-violet-400 hover:text-violet-300 transition-colors"
                                        title="Open in Coolify"
                                    >
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                        </svg>
                                    </a>
                                </div>
                                <div class="text-sm text-zinc-400">{{ cache.type }}</div>
                            </div>
                        </div>
                        <span :class="[statusClass(cache.status), 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize']">
                            {{ formatStatus(cache.status) }}
                        </span>
                    </div>
                    <div class="px-5 py-4 flex items-center gap-2">
                        <button
                            v-if="isStopped(cache.status)"
                            @click="cacheControl('start')"
                            :disabled="cacheAction"
                            class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-500 disabled:opacity-50 transition-colors"
                        >
                            <span v-if="cacheAction === 'start'" class="h-3 w-3 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                            Start
                        </button>
                        <button
                            v-if="isRunning(cache.status)"
                            @click="cacheControl('stop')"
                            :disabled="cacheAction"
                            class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-500 disabled:opacity-50 transition-colors"
                        >
                            <span v-if="cacheAction === 'stop'" class="h-3 w-3 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                            Stop
                        </button>
                        <button
                            v-if="isRunning(cache.status)"
                            @click="cacheControl('restart')"
                            :disabled="cacheAction"
                            class="inline-flex items-center gap-2 rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-zinc-700 disabled:opacity-50 transition-colors"
                        >
                            <span v-if="cacheAction === 'restart'" class="h-3 w-3 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                            Restart
                        </button>
                        <span v-if="isTransitioning(cache.status)" class="text-sm text-zinc-400 italic">{{ cache.status }}...</span>
                    </div>
                </div>
            </div>

            <!-- Recent Deployments -->
            <div class="rounded-xl border border-zinc-800 bg-zinc-900">
                <div class="border-b border-zinc-800 px-5 py-4 flex items-center justify-between">
                    <h2 class="text-lg font-medium text-white">Recent Deployments</h2>
                    <RouterLink to="/deployments" class="text-sm text-violet-400 hover:text-violet-300 transition-colors">
                        View all
                    </RouterLink>
                </div>
                <div class="divide-y divide-zinc-800">
                    <RouterLink
                        v-for="deployment in deployments.slice(0, 5)"
                        :key="deployment.uuid"
                        :to="`/deployments/${deployment.uuid}`"
                        class="flex items-center justify-between px-5 py-4 hover:bg-zinc-800/50 transition-colors"
                    >
                        <div class="flex items-center gap-4">
                            <span :class="[statusClass(deployment.status), 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize']">
                                {{ deployment.status?.replace('_', ' ') }}
                            </span>
                            <code class="text-xs text-zinc-500 bg-zinc-800 px-1.5 py-0.5 rounded">{{ deployment.commit?.substring(0, 7) || '-' }}</code>
                            <span class="text-sm text-white truncate max-w-md">{{ deployment.commit_message || 'No message' }}</span>
                        </div>
                        <div class="flex items-center gap-6 text-sm text-zinc-400">
                            <span>{{ formatDuration(deployment.duration) }}</span>
                            <span>{{ formatDate(deployment.created_at) }}</span>
                        </div>
                    </RouterLink>
                    <div v-if="!deployments.length" class="px-5 py-8 text-center text-sm text-zinc-500">
                        No deployments yet
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
