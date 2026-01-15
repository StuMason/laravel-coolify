<script setup>
import { inject, computed, ref, reactive } from 'vue';
import { RouterLink } from 'vue-router';

const stats = inject('stats');
const api = inject('api');
const refreshStats = inject('refreshStats');
const toast = inject('toast');

const deployments = computed(() => stats.value?.recentDeployments || []);
const app = computed(() => stats.value?.application || {});

const deploying = ref(false);
const redeployingCommit = ref(null);
const showDeployMenu = ref(false);
const showCommitInput = ref(false);
const commitSha = ref('');

// Accordion logs state
const expandedLogs = ref(new Set());
const logsData = reactive({});
const logsLoading = reactive({});
const logsExpanded = reactive({}); // "See more" expansion state

// Deploy functions
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

async function redeployCommit(commit) {
    if (redeployingCommit.value || !commit) return;
    redeployingCommit.value = commit;
    try {
        await api.deployApplication(app.value.uuid, { commit });
        toast.value?.success('Deployment Started', `Redeploying commit ${commit.substring(0, 7)}`);
        await refreshStats();
    } catch (e) {
        toast.value?.error('Deployment Failed', e.message);
    } finally {
        redeployingCommit.value = null;
    }
}

function deployCommit() {
    if (!commitSha.value.trim()) {
        toast.value?.error('Invalid Commit', 'Please enter a commit SHA');
        return;
    }
    deploy({ commit: commitSha.value.trim() });
}

// Copy to clipboard
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
    toast.value?.success('Copied', 'Commit SHA copied to clipboard');
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

function statusClass(status) {
    if (status === 'finished') return 'bg-emerald-500/10 text-emerald-400';
    if (status === 'failed') return 'bg-red-500/10 text-red-400';
    if (status === 'in_progress') return 'bg-amber-500/10 text-amber-400 animate-pulse-status';
    if (status === 'queued') return 'bg-blue-500/10 text-blue-400';
    if (status === 'cancelled') return 'bg-zinc-500/10 text-zinc-400';
    return 'bg-zinc-500/10 text-zinc-400';
}

function statusIcon(status) {
    if (status === 'finished') return 'check';
    if (status === 'failed') return 'x';
    if (status === 'in_progress') return 'loading';
    if (status === 'queued') return 'clock';
    if (status === 'cancelled') return 'stop';
    return 'unknown';
}

// Get GitHub commit URL
function getCommitUrl(commit) {
    if (!commit || !app.value?.repository) return null;
    const repo = app.value.repository.replace('git@github.com:', '').replace('.git', '');
    return `https://github.com/${repo}/commit/${commit}`;
}

// Get unique commits from deployments
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

// Toggle logs accordion
async function toggleLogs(deploymentUuid) {
    if (expandedLogs.value.has(deploymentUuid)) {
        expandedLogs.value.delete(deploymentUuid);
        expandedLogs.value = new Set(expandedLogs.value); // Trigger reactivity
        return;
    }

    expandedLogs.value.add(deploymentUuid);
    expandedLogs.value = new Set(expandedLogs.value);

    // Fetch logs if not already loaded
    if (!logsData[deploymentUuid]) {
        await fetchLogs(deploymentUuid);
    }
}

async function fetchLogs(deploymentUuid) {
    logsLoading[deploymentUuid] = true;
    try {
        const data = await api.getDeployment(deploymentUuid);
        let logs = [];

        // Parse logs - they come as JSON string
        if (data.logs) {
            try {
                logs = typeof data.logs === 'string' ? JSON.parse(data.logs) : data.logs;
            } catch {
                // If not JSON, treat as plain text
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

// Format log output for display
function formatLogLine(line) {
    if (!line) return '';
    // Strip ANSI codes for cleaner display
    return line.output?.replace(/\x1b\[[0-9;]*m/g, '') || line.toString();
}

// Get log lines - preview (first 8) or all
function getLogLines(deploymentUuid, preview = true) {
    const logs = logsData[deploymentUuid] || [];
    if (!preview || logsExpanded[deploymentUuid]) {
        return logs;
    }
    return logs.slice(0, 8);
}

function hasMoreLogs(deploymentUuid) {
    const logs = logsData[deploymentUuid] || [];
    return logs.length > 8;
}

function getLogLineCount(deploymentUuid) {
    return logsData[deploymentUuid]?.length || 0;
}
</script>

<template>
    <div class="p-6 space-y-6">
        <!-- Header with deploy actions -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-white">Deployments</h1>
                <p class="mt-1 text-sm text-zinc-400">
                    History of all deployments for {{ app.name || 'your application' }}
                    <span v-if="app.branch" class="text-zinc-500">
                        on <code class="text-xs bg-zinc-800 px-1.5 py-0.5 rounded">{{ app.branch }}</code>
                    </span>
                </p>
            </div>

            <!-- Deploy dropdown -->
            <div class="relative">
                <div class="flex">
                    <button
                        @click="deploy()"
                        :disabled="deploying"
                        class="inline-flex items-center gap-2 rounded-l-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-500 disabled:opacity-50 transition-colors"
                    >
                        <span v-if="deploying" class="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                        <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 0 1-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 0 0 6.16-12.12A14.98 14.98 0 0 0 9.631 8.41m5.96 5.96a14.926 14.926 0 0 1-5.841 2.58m-.119-8.54a6 6 0 0 0-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 0 0-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 0 1-2.448-2.448 14.9 14.9 0 0 1 .06-.312m-2.24 2.39a4.493 4.493 0 0 0-1.757 4.306 4.493 4.493 0 0 0 4.306-1.758M16.5 9a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" />
                        </svg>
                        Deploy Latest
                    </button>
                    <button
                        @click="showDeployMenu = !showDeployMenu"
                        :disabled="deploying"
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

                    <!-- Previous commits -->
                    <div v-if="uniqueCommits.length" class="border-t border-zinc-700 py-1">
                        <div class="px-4 py-2 text-xs font-medium text-zinc-500 uppercase tracking-wide">Redeploy Previous</div>
                        <button
                            v-for="d in uniqueCommits"
                            :key="d.uuid"
                            @click="redeployCommit(d.commit); showDeployMenu = false"
                            class="w-full px-4 py-2 text-left text-sm text-white hover:bg-zinc-700 flex items-center gap-3"
                        >
                            <code class="text-xs text-zinc-400 bg-zinc-700 px-1.5 py-0.5 rounded flex-shrink-0">{{ d.commit?.substring(0, 7) }}</code>
                            <span class="truncate text-zinc-300 flex-1">{{ d.commit_message || 'No message' }}</span>
                            <span class="text-xs text-zinc-500 flex-shrink-0">{{ formatRelativeTime(d.created_at) }}</span>
                        </button>
                    </div>
                </div>

                <!-- Commit input modal -->
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

        <!-- Click outside handler -->
        <div
            v-if="showDeployMenu || showCommitInput"
            @click="showDeployMenu = false; showCommitInput = false"
            class="fixed inset-0 z-0"
        ></div>

        <!-- Deployments list -->
        <div class="rounded-xl border border-zinc-800 bg-zinc-900 overflow-hidden">
            <div v-if="deployments.length" class="divide-y divide-zinc-800">
                <div
                    v-for="deployment in deployments"
                    :key="deployment.uuid"
                    class="transition-colors"
                >
                    <!-- Main row -->
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
                                    <svg class="h-4 w-4 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 7.5A2.25 2.25 0 0 1 7.5 5.25h9a2.25 2.25 0 0 1 2.25 2.25v9a2.25 2.25 0 0 1-2.25 2.25h-9a2.25 2.25 0 0 1-2.25-2.25v-9Z" />
                                    </svg>
                                </div>
                            </div>

                            <!-- Main content -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-3 mb-1">
                                    <!-- Commit SHA with copy & link -->
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
                                        <button
                                            v-if="deployment.commit"
                                            @click.stop="copyToClipboard(deployment.commit)"
                                            class="p-1 text-zinc-500 hover:text-white transition-colors"
                                            title="Copy full SHA"
                                        >
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                            </svg>
                                        </button>
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
                                    <span v-if="deployment.created_at !== deployment.updated_at && deployment.status === 'finished'">
                                        Completed {{ formatRelativeTime(deployment.updated_at) }}
                                    </span>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <button
                                    v-if="deployment.commit && deployment.status !== 'in_progress' && deployment.status !== 'queued'"
                                    @click="redeployCommit(deployment.commit)"
                                    :disabled="redeployingCommit === deployment.commit"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-xs font-medium text-white hover:bg-zinc-700 disabled:opacity-50 transition-colors"
                                >
                                    <span v-if="redeployingCommit === deployment.commit" class="h-3 w-3 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                                    <svg v-else class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                    </svg>
                                    Redeploy
                                </button>
                                <!-- Toggle logs button -->
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
                        <!-- Loading state -->
                        <div v-if="logsLoading[deployment.uuid]" class="px-5 py-8 text-center">
                            <div class="inline-flex items-center gap-2 text-sm text-zinc-400">
                                <span class="h-4 w-4 animate-spin rounded-full border-2 border-zinc-500 border-t-white"></span>
                                Loading logs...
                            </div>
                        </div>

                        <!-- Logs content -->
                        <div v-else class="font-mono text-xs">
                            <!-- Log lines -->
                            <div class="max-h-96 overflow-y-auto">
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

                            <!-- No logs message -->
                            <div v-if="!getLogLines(deployment.uuid).length" class="px-5 py-8 text-center text-zinc-500">
                                No logs available for this deployment
                            </div>

                            <!-- See more / See less -->
                            <div v-if="hasMoreLogs(deployment.uuid)" class="border-t border-zinc-800">
                                <button
                                    @click="toggleLogsExpansion(deployment.uuid)"
                                    class="w-full px-5 py-3 text-center text-sm font-medium text-violet-400 hover:text-violet-300 hover:bg-zinc-900 transition-colors flex items-center justify-center gap-2"
                                >
                                    <span v-if="!logsExpanded[deployment.uuid]">
                                        See more ({{ getLogLineCount(deployment.uuid) - 8 }} more lines)
                                    </span>
                                    <span v-else>
                                        See less
                                    </span>
                                    <svg
                                        class="h-4 w-4 transition-transform duration-200"
                                        :class="{ 'rotate-180': logsExpanded[deployment.uuid] }"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                            </div>

                            <!-- Open full logs link -->
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

            <div v-else class="px-5 py-16 text-center">
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
    </div>
</template>
