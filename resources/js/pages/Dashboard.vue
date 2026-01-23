<script setup>
import { inject, computed, ref, reactive } from 'vue';
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
const showDeployMenu = ref(false);
const showCommitInput = ref(false);
const commitSha = ref('');

// Accordion logs state
const expandedLogs = ref(new Set());
const logsData = reactive({});
const logsLoading = reactive({});
const logsExpanded = reactive({});

const app = computed(() => stats.value?.application || {});
const deployments = computed(() => stats.value?.recentDeployments || []);
const database = computed(() => stats.value?.databases?.primary);
const cache = computed(() => stats.value?.databases?.redis);
const connected = computed(() => stats.value?.connected);
const server = computed(() => stats.value?.server);
const project = computed(() => stats.value?.project);
const environment = computed(() => stats.value?.environment);

// Latest deployment
const latestDeployment = computed(() => deployments.value[0] || null);
const currentCommit = computed(() => latestDeployment.value?.commit || app.value?.git_commit_sha);

// Coolify URLs
const coolifyUrl = computed(() => {
    const baseUrl = window.Coolify?.coolifyUrl || stats.value?.coolify_url;
    if (!baseUrl) return null;
    return baseUrl.replace(/\/$/, '');
});

const appCoolifyUrl = computed(() => {
    if (!coolifyUrl.value || !app.value?.uuid) return null;
    const projectUuid = project.value?.uuid;
    const envName = environment.value?.name || 'production';
    if (projectUuid) {
        return `${coolifyUrl.value}/project/${projectUuid}/${envName}/application/${app.value.uuid}`;
    }
    return `${coolifyUrl.value}/application/${app.value.uuid}`;
});

const dbCoolifyUrl = computed(() => {
    if (!coolifyUrl.value || !database.value?.uuid) return null;
    const projectUuid = project.value?.uuid;
    const envName = environment.value?.name || 'production';
    if (projectUuid) {
        return `${coolifyUrl.value}/project/${projectUuid}/${envName}/database/${database.value.uuid}`;
    }
    return `${coolifyUrl.value}/database/${database.value.uuid}`;
});

const cacheCoolifyUrl = computed(() => {
    if (!coolifyUrl.value || !cache.value?.uuid) return null;
    const projectUuid = project.value?.uuid;
    const envName = environment.value?.name || 'production';
    if (projectUuid) {
        return `${coolifyUrl.value}/project/${projectUuid}/${envName}/database/${cache.value.uuid}`;
    }
    return `${coolifyUrl.value}/database/${cache.value.uuid}`;
});

// GitHub URL helper
function getCommitUrl(commit) {
    if (!commit || !app.value?.repository) return null;
    const repo = app.value.repository.replace('git@github.com:', '').replace('.git', '');
    return `https://github.com/${repo}/commit/${commit}`;
}

function getRepoUrl() {
    if (!app.value?.repository) return null;
    const repo = app.value.repository.replace('git@github.com:', '').replace('.git', '');
    return `https://github.com/${repo}`;
}

function getBranchUrl() {
    if (!app.value?.repository || !app.value?.branch) return null;
    const repo = app.value.repository.replace('git@github.com:', '').replace('.git', '');
    return `https://github.com/${repo}/tree/${app.value.branch}`;
}

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

function statusIcon(status) {
    const s = status?.toLowerCase();
    if (s === 'finished' || s === 'running' || s === 'running:healthy' || s === 'healthy') return 'check';
    if (s === 'failed' || s === 'stopped' || s === 'exited' || s === 'error' || s === 'exited:unhealthy') return 'x';
    if (s === 'in_progress' || s === 'starting' || s === 'stopping' || s === 'restarting' || s === 'building') return 'loading';
    if (s === 'queued') return 'clock';
    return 'unknown';
}

// App actions
async function deploy(options = {}) {
    if (deploying.value) return;
    deploying.value = true;
    showDeployMenu.value = false;
    showCommitInput.value = false;
    try {
        await api.deployApplication(app.value.uuid, options);
        const msg = options.force
            ? 'Force rebuild started'
            : options.commit
                ? `Deploying commit ${options.commit.substring(0, 7)}`
                : 'Deploying latest';
        toast.value?.success('Deployment Started', msg);
        commitSha.value = '';
        await refreshStats();
    } catch (e) {
        toast.value?.error('Deployment Failed', e.message);
    } finally {
        deploying.value = false;
    }
}

function deployCommit() {
    if (!commitSha.value.trim()) {
        toast.value?.error('Invalid Commit', 'Please enter a commit SHA');
        return;
    }
    deploy({ commit: commitSha.value.trim() });
}

function deployFromHistory(commit) {
    if (commit) {
        deploy({ commit });
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

// Clipboard
function copyToClipboard(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text);
    } else {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
    }
    toast.value?.success('Copied', 'Copied to clipboard');
}

// Logs accordion functions
async function toggleLogs(deploymentUuid) {
    if (expandedLogs.value.has(deploymentUuid)) {
        expandedLogs.value.delete(deploymentUuid);
        expandedLogs.value = new Set(expandedLogs.value);
        return;
    }

    expandedLogs.value.add(deploymentUuid);
    expandedLogs.value = new Set(expandedLogs.value);

    if (!logsData[deploymentUuid]) {
        await fetchLogs(deploymentUuid);
    }
}

async function fetchLogs(deploymentUuid) {
    logsLoading[deploymentUuid] = true;
    try {
        const data = await api.getDeployment(deploymentUuid);
        let logs = [];

        if (data.logs) {
            try {
                logs = typeof data.logs === 'string' ? JSON.parse(data.logs) : data.logs;
            } catch {
                logs = [{ output: data.logs, type: 'stdout' }];
            }
        }

        logsData[deploymentUuid] = logs;
    } catch (e) {
        logsData[deploymentUuid] = [{ output: `Failed to load logs: ${e.message}`, type: 'stderr' }];
    } finally {
        logsLoading[deploymentUuid] = false;
    }
}

function toggleLogsExpansion(deploymentUuid) {
    logsExpanded[deploymentUuid] = !logsExpanded[deploymentUuid];
}

function isLogsExpanded(deploymentUuid) {
    return expandedLogs.value.has(deploymentUuid);
}

function formatLogLine(line) {
    if (!line) return '';
    return line.output?.replace(/\x1b\[[0-9;]*m/g, '') || line.toString();
}

function getLogLines(deploymentUuid, preview = true) {
    const logs = logsData[deploymentUuid] || [];
    if (!preview || logsExpanded[deploymentUuid]) {
        return logs;
    }
    return logs.slice(0, 6);
}

function hasMoreLogs(deploymentUuid) {
    const logs = logsData[deploymentUuid] || [];
    return logs.length > 6;
}

function getLogLineCount(deploymentUuid) {
    return logsData[deploymentUuid]?.length || 0;
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

function formatRelativeTime(date) {
    if (!date) return '-';
    const now = new Date();
    const then = new Date(date);
    const diffMs = now - then;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    if (diffDays < 7) return `${diffDays}d ago`;
    return formatDate(date);
}

function formatDuration(seconds) {
    if (!seconds) return '-';
    if (seconds < 60) return `${seconds}s`;
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}m ${secs}s`;
}

// Unique commits for deploy dropdown
const uniqueCommits = computed(() => {
    const seen = new Set();
    return deployments.value
        .filter(d => {
            if (!d.commit || seen.has(d.commit)) return false;
            seen.add(d.commit);
            return true;
        })
        .slice(0, 5);
});
</script>

<template>
    <div class="p-6 space-y-6">
        <!-- Not connected -->
        <div v-if="!connected" class="rounded-xl border border-red-500/20 bg-red-500/5 p-8 text-center">
            <svg class="mx-auto h-12 w-12 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
            </svg>
            <h3 class="mt-4 text-lg font-medium text-white">Not Connected</h3>
            <p class="mt-2 text-sm text-zinc-400">Unable to connect to Coolify. Check your configuration.</p>
        </div>

        <template v-else>
            <!-- Hero Status Card -->
            <div class="rounded-xl border border-zinc-800 bg-zinc-900 overflow-hidden">
                <div class="p-6">
                    <div class="flex items-start justify-between">
                        <!-- App Info -->
                        <div class="flex-1">
                            <div class="flex items-center gap-4">
                                <!-- Status indicator -->
                                <div
                                    class="flex h-14 w-14 items-center justify-center rounded-xl"
                                    :class="{
                                        'bg-emerald-500/10': isRunning(app.status),
                                        'bg-red-500/10': isStopped(app.status),
                                        'bg-blue-500/10': isTransitioning(app.status),
                                        'bg-zinc-500/10': !app.status
                                    }"
                                >
                                    <div v-if="isRunning(app.status)" class="relative">
                                        <div class="absolute inset-0 flex items-center justify-center">
                                            <div class="h-3 w-3 rounded-full bg-emerald-400 animate-ping opacity-75"></div>
                                        </div>
                                        <div class="h-3 w-3 rounded-full bg-emerald-400"></div>
                                    </div>
                                    <svg v-else-if="isStopped(app.status)" class="h-6 w-6 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 7.5A2.25 2.25 0 0 1 7.5 5.25h9a2.25 2.25 0 0 1 2.25 2.25v9a2.25 2.25 0 0 1-2.25 2.25h-9a2.25 2.25 0 0 1-2.25-2.25v-9Z" />
                                    </svg>
                                    <svg v-else-if="isTransitioning(app.status)" class="h-6 w-6 text-blue-400 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <div v-else class="h-3 w-3 rounded-full bg-zinc-400"></div>
                                </div>
                                <div>
                                    <h1 class="text-2xl font-semibold text-white">{{ app.name || 'Application' }}</h1>
                                    <div class="mt-1 flex items-center gap-3 text-sm">
                                        <span :class="[statusColor(app.status), 'font-medium capitalize']">{{ formatStatus(app.status) }}</span>
                                        <span class="text-zinc-600">|</span>
                                        <span class="text-zinc-400">{{ app.build_pack || 'Unknown' }}</span>
                                        <span v-if="server" class="text-zinc-600">|</span>
                                        <span v-if="server" class="text-zinc-500">{{ server.name }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Quick Links -->
                            <div class="mt-4 flex flex-wrap items-center gap-4">
                                <a
                                    v-if="app.fqdn"
                                    :href="app.fqdn"
                                    target="_blank"
                                    class="inline-flex items-center gap-1.5 text-sm text-zinc-400 hover:text-white transition-colors"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5a17.92 17.92 0 0 1-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" />
                                    </svg>
                                    {{ app.fqdn.replace(/^https?:\/\//, '') }}
                                </a>
                                <a
                                    v-if="getRepoUrl()"
                                    :href="getRepoUrl()"
                                    target="_blank"
                                    class="inline-flex items-center gap-1.5 text-sm text-zinc-400 hover:text-white transition-colors"
                                >
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                                    </svg>
                                    GitHub
                                </a>
                                <a
                                    v-if="appCoolifyUrl"
                                    :href="appCoolifyUrl"
                                    target="_blank"
                                    class="inline-flex items-center gap-1.5 text-sm text-violet-400 hover:text-violet-300 transition-colors"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                    </svg>
                                    Open in Coolify
                                </a>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-2">
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

                            <!-- Deploy dropdown -->
                            <div class="relative">
                                <div class="flex">
                                    <button
                                        @click="deploy()"
                                        :disabled="deploying || isTransitioning(app.status)"
                                        class="inline-flex items-center gap-2 rounded-l-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-500 disabled:opacity-50 transition-colors"
                                    >
                                        <span v-if="deploying" class="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                                        <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 0 1-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 0 0 6.16-12.12A14.98 14.98 0 0 0 9.631 8.41m5.96 5.96a14.926 14.926 0 0 1-5.841 2.58m-.119-8.54a6 6 0 0 0-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 0 0-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 0 1-2.448-2.448 14.9 14.9 0 0 1 .06-.312m-2.24 2.39a4.493 4.493 0 0 0-1.757 4.306 4.493 4.493 0 0 0 4.306-1.758M16.5 9a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" />
                                        </svg>
                                        Deploy
                                    </button>
                                    <button
                                        @click="showDeployMenu = !showDeployMenu"
                                        :disabled="deploying || isTransitioning(app.status)"
                                        class="inline-flex items-center rounded-r-lg border-l border-violet-700 bg-violet-600 px-2 py-2 text-sm font-medium text-white hover:bg-violet-500 disabled:opacity-50 transition-colors"
                                    >
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                </div>

                                <!-- Deploy menu -->
                                <div
                                    v-if="showDeployMenu"
                                    class="absolute right-0 z-10 mt-2 w-72 origin-top-right rounded-lg border border-zinc-700 bg-zinc-800 shadow-xl"
                                >
                                    <div class="py-1">
                                        <button
                                            @click="deploy()"
                                            class="w-full px-4 py-2.5 text-left text-sm text-white hover:bg-zinc-700 flex items-center gap-3"
                                        >
                                            <svg class="h-4 w-4 text-emerald-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                            </svg>
                                            <div>
                                                <div class="font-medium">Deploy Latest</div>
                                                <div class="text-xs text-zinc-400">Deploy HEAD from {{ app.branch || 'main' }}</div>
                                            </div>
                                        </button>
                                        <button
                                            @click="deploy({ force: true })"
                                            class="w-full px-4 py-2.5 text-left text-sm text-white hover:bg-zinc-700 flex items-center gap-3"
                                        >
                                            <svg class="h-4 w-4 text-amber-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                            </svg>
                                            <div>
                                                <div class="font-medium">Force Rebuild</div>
                                                <div class="text-xs text-zinc-400">Rebuild without Docker cache</div>
                                            </div>
                                        </button>
                                        <button
                                            @click="showCommitInput = true; showDeployMenu = false"
                                            class="w-full px-4 py-2.5 text-left text-sm text-white hover:bg-zinc-700 flex items-center gap-3"
                                        >
                                            <svg class="h-4 w-4 text-blue-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75 22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3-4.5 16.5" />
                                            </svg>
                                            <div>
                                                <div class="font-medium">Deploy Specific Commit</div>
                                                <div class="text-xs text-zinc-400">Enter a commit SHA to deploy</div>
                                            </div>
                                        </button>
                                    </div>

                                    <div v-if="uniqueCommits.length" class="border-t border-zinc-700 py-1">
                                        <div class="px-4 py-2 text-xs font-medium text-zinc-500 uppercase tracking-wide">Redeploy Previous</div>
                                        <button
                                            v-for="d in uniqueCommits"
                                            :key="d.uuid"
                                            @click="deployFromHistory(d.commit); showDeployMenu = false"
                                            class="w-full px-4 py-2 text-left text-sm text-white hover:bg-zinc-700 flex items-center gap-3"
                                        >
                                            <code class="text-xs text-zinc-400 bg-zinc-700 px-1.5 py-0.5 rounded flex-shrink-0">{{ d.commit?.substring(0, 7) }}</code>
                                            <span class="truncate text-zinc-300 flex-1">{{ d.commit_message || 'No message' }}</span>
                                            <span class="text-xs text-zinc-500 flex-shrink-0">{{ formatRelativeTime(d.created_at) }}</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Commit input -->
                                <div
                                    v-if="showCommitInput"
                                    class="absolute right-0 z-10 mt-2 w-96 origin-top-right rounded-lg border border-zinc-700 bg-zinc-800 shadow-xl p-4"
                                >
                                    <div class="text-sm font-medium text-white mb-2">Deploy Specific Commit</div>
                                    <p class="text-xs text-zinc-400 mb-3">Enter a full commit SHA from your repository</p>
                                    <input
                                        v-model="commitSha"
                                        type="text"
                                        placeholder="e.g. abc123def456789..."
                                        class="w-full rounded-lg border border-zinc-600 bg-zinc-700 px-3 py-2 text-sm text-white placeholder-zinc-400 focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500 font-mono"
                                        @keyup.enter="deployCommit"
                                    />
                                    <div class="mt-3 flex justify-end gap-2">
                                        <button
                                            @click="showCommitInput = false; commitSha = ''"
                                            class="rounded-lg border border-zinc-600 bg-zinc-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-zinc-600"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            @click="deployCommit"
                                            :disabled="!commitSha.trim()"
                                            class="rounded-lg bg-violet-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-violet-500 disabled:opacity-50"
                                        >
                                            Deploy
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Current Deployment Info Bar -->
                <div class="border-t border-zinc-800 bg-zinc-950 px-6 py-3 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <div class="text-zinc-500 text-xs uppercase tracking-wide mb-1">Branch</div>
                        <a
                            v-if="getBranchUrl()"
                            :href="getBranchUrl()"
                            target="_blank"
                            class="text-white hover:text-violet-400 transition-colors inline-flex items-center gap-1"
                        >
                            <svg class="h-3.5 w-3.5 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                            </svg>
                            {{ app.branch || '-' }}
                        </a>
                        <span v-else class="text-white">{{ app.branch || '-' }}</span>
                    </div>
                    <div>
                        <div class="text-zinc-500 text-xs uppercase tracking-wide mb-1">Current Commit</div>
                        <div class="flex items-center gap-1.5">
                            <a
                                v-if="getCommitUrl(currentCommit)"
                                :href="getCommitUrl(currentCommit)"
                                target="_blank"
                                class="inline-flex items-center gap-1 text-xs font-mono bg-zinc-800 hover:bg-zinc-700 text-zinc-300 hover:text-white px-2 py-0.5 rounded transition-colors"
                            >
                                <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                                </svg>
                                {{ currentCommit?.substring(0, 7) || '-------' }}
                            </a>
                            <code v-else class="text-xs font-mono text-zinc-400">{{ currentCommit?.substring(0, 7) || '-' }}</code>
                            <button
                                v-if="currentCommit"
                                @click="copyToClipboard(currentCommit)"
                                class="p-0.5 text-zinc-500 hover:text-white transition-colors"
                                title="Copy full SHA"
                            >
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div>
                        <div class="text-zinc-500 text-xs uppercase tracking-wide mb-1">Last Deploy</div>
                        <span class="text-white">{{ formatRelativeTime(latestDeployment?.created_at) }}</span>
                        <span v-if="latestDeployment?.duration" class="text-zinc-500 ml-1">({{ formatDuration(latestDeployment.duration) }})</span>
                    </div>
                    <div>
                        <div class="text-zinc-500 text-xs uppercase tracking-wide mb-1">Project</div>
                        <div class="flex items-center gap-2">
                            <span class="text-white">{{ project?.name || '-' }}</span>
                            <span v-if="environment" class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-emerald-500/10 text-emerald-400 ring-1 ring-inset ring-emerald-500/20">
                                {{ environment.name }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Click outside to close dropdowns -->
            <div
                v-if="showDeployMenu || showCommitInput"
                @click="showDeployMenu = false; showCommitInput = false"
                class="fixed inset-0 z-0"
            ></div>

            <!-- Resources Grid -->
            <div class="grid gap-4 lg:grid-cols-2" v-if="database || cache">
                <!-- Database Card -->
                <div v-if="database" class="rounded-xl border border-zinc-800 bg-zinc-900 overflow-hidden">
                    <div class="px-5 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-500/10">
                                <svg class="h-5 w-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                                </svg>
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-white">{{ database.name }}</span>
                                    <span :class="[statusClass(database.status), 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize']">
                                        {{ formatStatus(database.status) }}
                                    </span>
                                </div>
                                <div class="text-sm text-zinc-500">{{ database.type }} {{ database.image ? `(${database.image})` : '' }}</div>
                            </div>
                        </div>
                        <a
                            v-if="dbCoolifyUrl"
                            :href="dbCoolifyUrl"
                            target="_blank"
                            class="text-violet-400 hover:text-violet-300 transition-colors p-2"
                            title="Open in Coolify"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                            </svg>
                        </a>
                    </div>
                    <div class="border-t border-zinc-800 bg-zinc-950 px-5 py-3 text-xs grid grid-cols-2 gap-4">
                        <div>
                            <span class="text-zinc-500">Internal Host:</span>
                            <code class="ml-1 text-zinc-300">{{ database.internal_db_url || database.name }}</code>
                        </div>
                        <div v-if="database.public_port">
                            <span class="text-zinc-500">Public Port:</span>
                            <code class="ml-1 text-zinc-300">{{ database.public_port }}</code>
                        </div>
                    </div>
                    <div class="border-t border-zinc-800 px-5 py-3 flex items-center gap-2">
                        <button
                            v-if="isStopped(database.status)"
                            @click="dbControl('start')"
                            :disabled="dbAction"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-500 disabled:opacity-50 transition-colors"
                        >
                            <span v-if="dbAction === 'start'" class="h-3 w-3 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                            Start
                        </button>
                        <button
                            v-if="isRunning(database.status)"
                            @click="dbControl('stop')"
                            :disabled="dbAction"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-xs font-medium text-white hover:bg-zinc-700 disabled:opacity-50 transition-colors"
                        >
                            <span v-if="dbAction === 'stop'" class="h-3 w-3 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                            Stop
                        </button>
                        <button
                            v-if="isRunning(database.status)"
                            @click="dbControl('restart')"
                            :disabled="dbAction"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-xs font-medium text-white hover:bg-zinc-700 disabled:opacity-50 transition-colors"
                        >
                            <span v-if="dbAction === 'restart'" class="h-3 w-3 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                            Restart
                        </button>
                        <RouterLink
                            to="/resources"
                            class="ml-auto text-xs text-zinc-500 hover:text-white transition-colors"
                        >
                            View details
                        </RouterLink>
                    </div>
                </div>

                <!-- Cache Card -->
                <div v-if="cache" class="rounded-xl border border-zinc-800 bg-zinc-900 overflow-hidden">
                    <div class="px-5 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-500/10">
                                <svg class="h-5 w-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                                </svg>
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-white">{{ cache.name }}</span>
                                    <span :class="[statusClass(cache.status), 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize']">
                                        {{ formatStatus(cache.status) }}
                                    </span>
                                </div>
                                <div class="text-sm text-zinc-500">{{ cache.type }} {{ cache.image ? `(${cache.image})` : '' }}</div>
                            </div>
                        </div>
                        <a
                            v-if="cacheCoolifyUrl"
                            :href="cacheCoolifyUrl"
                            target="_blank"
                            class="text-violet-400 hover:text-violet-300 transition-colors p-2"
                            title="Open in Coolify"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                            </svg>
                        </a>
                    </div>
                    <div class="border-t border-zinc-800 bg-zinc-950 px-5 py-3 text-xs grid grid-cols-2 gap-4">
                        <div>
                            <span class="text-zinc-500">Internal Host:</span>
                            <code class="ml-1 text-zinc-300">{{ cache.internal_db_url || cache.name }}</code>
                        </div>
                        <div v-if="cache.public_port">
                            <span class="text-zinc-500">Public Port:</span>
                            <code class="ml-1 text-zinc-300">{{ cache.public_port }}</code>
                        </div>
                    </div>
                    <div class="border-t border-zinc-800 px-5 py-3 flex items-center gap-2">
                        <button
                            v-if="isStopped(cache.status)"
                            @click="cacheControl('start')"
                            :disabled="cacheAction"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-500 disabled:opacity-50 transition-colors"
                        >
                            <span v-if="cacheAction === 'start'" class="h-3 w-3 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                            Start
                        </button>
                        <button
                            v-if="isRunning(cache.status)"
                            @click="cacheControl('stop')"
                            :disabled="cacheAction"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-xs font-medium text-white hover:bg-zinc-700 disabled:opacity-50 transition-colors"
                        >
                            <span v-if="cacheAction === 'stop'" class="h-3 w-3 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                            Stop
                        </button>
                        <button
                            v-if="isRunning(cache.status)"
                            @click="cacheControl('restart')"
                            :disabled="cacheAction"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-xs font-medium text-white hover:bg-zinc-700 disabled:opacity-50 transition-colors"
                        >
                            <span v-if="cacheAction === 'restart'" class="h-3 w-3 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                            Restart
                        </button>
                        <RouterLink
                            to="/resources"
                            class="ml-auto text-xs text-zinc-500 hover:text-white transition-colors"
                        >
                            View details
                        </RouterLink>
                    </div>
                </div>
            </div>

            <!-- Recent Deployments with Accordion Logs -->
            <div class="rounded-xl border border-zinc-800 bg-zinc-900 overflow-hidden">
                <div class="border-b border-zinc-800 px-5 py-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-medium text-white">Recent Deployments</h2>
                        <p class="text-sm text-zinc-500">{{ deployments.length }} deployment{{ deployments.length !== 1 ? 's' : '' }} in history</p>
                    </div>
                    <RouterLink to="/deployments" class="text-sm text-violet-400 hover:text-violet-300 transition-colors inline-flex items-center gap-1">
                        View all
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </RouterLink>
                </div>
                <div v-if="deployments.length" class="divide-y divide-zinc-800">
                    <div
                        v-for="deployment in deployments.slice(0, 5)"
                        :key="deployment.uuid"
                        class="transition-colors"
                    >
                        <!-- Deployment row -->
                        <div class="px-5 py-4 hover:bg-zinc-800/30">
                            <div class="flex items-start gap-4">
                                <!-- Status icon -->
                                <div class="flex-shrink-0 mt-0.5">
                                    <div v-if="statusIcon(deployment.status) === 'check'" class="h-8 w-8 rounded-full bg-emerald-500/10 flex items-center justify-center">
                                        <svg class="h-4 w-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                        </svg>
                                    </div>
                                    <div v-else-if="statusIcon(deployment.status) === 'x'" class="h-8 w-8 rounded-full bg-red-500/10 flex items-center justify-center">
                                        <svg class="h-4 w-4 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </div>
                                    <div v-else-if="statusIcon(deployment.status) === 'loading'" class="h-8 w-8 rounded-full bg-amber-500/10 flex items-center justify-center">
                                        <svg class="h-4 w-4 text-amber-400 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </div>
                                    <div v-else-if="statusIcon(deployment.status) === 'clock'" class="h-8 w-8 rounded-full bg-blue-500/10 flex items-center justify-center">
                                        <svg class="h-4 w-4 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </svg>
                                    </div>
                                    <div v-else class="h-8 w-8 rounded-full bg-zinc-500/10 flex items-center justify-center">
                                        <div class="h-2 w-2 rounded-full bg-zinc-400"></div>
                                    </div>
                                </div>

                                <!-- Main content -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-3 mb-1">
                                        <!-- Commit SHA -->
                                        <div class="flex items-center gap-1.5">
                                            <a
                                                v-if="getCommitUrl(deployment.commit)"
                                                :href="getCommitUrl(deployment.commit)"
                                                target="_blank"
                                                class="inline-flex items-center gap-1 text-xs font-mono bg-zinc-800 hover:bg-zinc-700 text-zinc-300 hover:text-white px-2 py-1 rounded transition-colors"
                                                @click.stop
                                            >
                                                <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                                                </svg>
                                                {{ deployment.commit?.substring(0, 7) || '-------' }}
                                            </a>
                                            <code v-else class="text-xs font-mono bg-zinc-800 text-zinc-400 px-2 py-1 rounded">
                                                {{ deployment.commit?.substring(0, 7) || '-------' }}
                                            </code>
                                        </div>

                                        <!-- Status badge -->
                                        <span :class="[statusClass(deployment.status), 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize']">
                                            {{ deployment.status?.replace('_', ' ') }}
                                        </span>

                                        <!-- Duration -->
                                        <span v-if="deployment.duration" class="text-xs text-zinc-500">
                                            {{ formatDuration(deployment.duration) }}
                                        </span>
                                    </div>

                                    <!-- Commit message -->
                                    <p class="text-sm text-white truncate">
                                        {{ deployment.commit_message || 'No commit message' }}
                                    </p>

                                    <!-- Meta info -->
                                    <div class="flex items-center gap-4 mt-1.5 text-xs text-zinc-500">
                                        <span>{{ formatRelativeTime(deployment.created_at) }}</span>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <button
                                        v-if="deployment.commit && deployment.status !== 'in_progress' && deployment.status !== 'queued'"
                                        @click="deployFromHistory(deployment.commit)"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-xs font-medium text-white hover:bg-zinc-700 transition-colors"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                        </svg>
                                        Redeploy
                                    </button>
                                    <button
                                        @click="toggleLogs(deployment.uuid)"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-xs font-medium text-white hover:bg-zinc-700 transition-colors"
                                        :class="{ 'bg-zinc-700 border-violet-500/50': isLogsExpanded(deployment.uuid) }"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 7.5l3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0021 18V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v12a2.25 2.25 0 002.25 2.25z" />
                                        </svg>
                                        <span>{{ isLogsExpanded(deployment.uuid) ? 'Hide' : 'Logs' }}</span>
                                        <svg
                                            class="h-3 w-3 transition-transform duration-200"
                                            :class="{ 'rotate-180': isLogsExpanded(deployment.uuid) }"
                                            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Accordion logs panel -->
                        <div
                            v-if="isLogsExpanded(deployment.uuid)"
                            class="bg-zinc-950 border-t border-zinc-800"
                        >
                            <div v-if="logsLoading[deployment.uuid]" class="px-5 py-6 text-center">
                                <div class="inline-flex items-center gap-2 text-sm text-zinc-400">
                                    <span class="h-4 w-4 animate-spin rounded-full border-2 border-zinc-500 border-t-white"></span>
                                    Loading logs...
                                </div>
                            </div>

                            <div v-else class="font-mono text-xs">
                                <div class="max-h-64 overflow-y-auto">
                                    <div
                                        v-for="(line, index) in getLogLines(deployment.uuid)"
                                        :key="index"
                                        class="px-5 py-0.5 hover:bg-zinc-900 border-l-2 flex"
                                        :class="{
                                            'border-transparent': line.type === 'stdout',
                                            'border-red-500 bg-red-500/5': line.type === 'stderr',
                                            'border-amber-500 bg-amber-500/5': line.type === 'warning'
                                        }"
                                    >
                                        <span class="text-zinc-600 select-none w-8 flex-shrink-0">{{ index + 1 }}</span>
                                        <span
                                            class="flex-1 whitespace-pre-wrap break-all"
                                            :class="{
                                                'text-zinc-300': line.type === 'stdout',
                                                'text-red-400': line.type === 'stderr',
                                                'text-amber-400': line.type === 'warning'
                                            }"
                                        >{{ formatLogLine(line) }}</span>
                                    </div>
                                </div>

                                <div v-if="!getLogLines(deployment.uuid).length" class="px-5 py-6 text-center text-zinc-500">
                                    No logs available for this deployment
                                </div>

                                <div v-if="hasMoreLogs(deployment.uuid)" class="border-t border-zinc-800">
                                    <button
                                        @click="toggleLogsExpansion(deployment.uuid)"
                                        class="w-full px-5 py-2.5 text-center text-xs font-medium text-violet-400 hover:text-violet-300 hover:bg-zinc-900 transition-colors flex items-center justify-center gap-2"
                                    >
                                        <span v-if="!logsExpanded[deployment.uuid]">
                                            See more ({{ getLogLineCount(deployment.uuid) - 6 }} more lines)
                                        </span>
                                        <span v-else>
                                            See less
                                        </span>
                                        <svg
                                            class="h-3.5 w-3.5 transition-transform duration-200"
                                            :class="{ 'rotate-180': logsExpanded[deployment.uuid] }"
                                            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                </div>

                                <div class="border-t border-zinc-800 px-5 py-2 flex justify-end">
                                    <RouterLink
                                        :to="`/deployments/${deployment.uuid}`"
                                        class="text-xs text-zinc-500 hover:text-white transition-colors inline-flex items-center gap-1"
                                    >
                                        Open full logs
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                        </svg>
                                    </RouterLink>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="px-5 py-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-zinc-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 0 1-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 0 0 6.16-12.12A14.98 14.98 0 0 0 9.631 8.41m5.96 5.96a14.926 14.926 0 0 1-5.841 2.58m-.119-8.54a6 6 0 0 0-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 0 0-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 0 1-2.448-2.448 14.9 14.9 0 0 1 .06-.312m-2.24 2.39a4.493 4.493 0 0 0-1.757 4.306 4.493 4.493 0 0 0 4.306-1.758M16.5 9a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" />
                    </svg>
                    <h3 class="mt-4 text-sm font-medium text-white">No deployments yet</h3>
                    <p class="mt-1 text-sm text-zinc-500">Get started by deploying your application</p>
                    <button
                        @click="deploy()"
                        :disabled="deploying"
                        class="mt-4 inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-500 disabled:opacity-50 transition-colors"
                    >
                        Deploy Now
                    </button>
                </div>
            </div>
        </template>
    </div>
</template>
