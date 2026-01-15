<script setup>
import { ref, onMounted, onUnmounted, inject } from 'vue';
import { useRoute, RouterLink } from 'vue-router';

const route = useRoute();
const api = inject('api');

const deployment = ref(null);
const logs = ref([]);
const loading = ref(true);
const logsLoading = ref(true);

let pollInterval = null;

async function fetchDeployment() {
    try {
        deployment.value = await api.getDeployment(route.params.uuid);
    } catch (e) {
        console.error('Failed to fetch deployment:', e);
    } finally {
        loading.value = false;
    }
}

async function fetchLogs() {
    try {
        const result = await api.getDeploymentLogs(route.params.uuid);
        // Logs come as array of {output, type} objects
        logs.value = Array.isArray(result.logs) ? result.logs : [];
    } catch (e) {
        console.error('Failed to fetch logs:', e);
    } finally {
        logsLoading.value = false;
    }
}

function formatDate(date) {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}

function statusClass(status) {
    if (status === 'finished') return 'bg-emerald-500/10 text-emerald-400';
    if (status === 'failed') return 'bg-red-500/10 text-red-400';
    if (status === 'in_progress') return 'bg-amber-500/10 text-amber-400';
    return 'bg-zinc-500/10 text-zinc-400';
}

// Format log output - strip ANSI codes
function formatLogLine(line) {
    if (!line) return '';
    const output = line.output || line.toString();
    return output.replace(/\x1b\[[0-9;]*m/g, '');
}

onMounted(async () => {
    await Promise.all([fetchDeployment(), fetchLogs()]);

    // Poll for updates if in progress
    if (deployment.value?.status === 'in_progress' || deployment.value?.status === 'queued') {
        pollInterval = setInterval(async () => {
            await Promise.all([fetchDeployment(), fetchLogs()]);
            if (deployment.value?.status !== 'in_progress' && deployment.value?.status !== 'queued') {
                clearInterval(pollInterval);
            }
        }, 3000);
    }
});

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
});
</script>

<template>
    <div class="p-6 space-y-6">
        <!-- Header -->
        <div class="flex items-center gap-4">
            <RouterLink
                to="/deployments"
                class="flex h-8 w-8 items-center justify-center rounded-lg border border-zinc-800 hover:bg-zinc-800 transition-colors"
            >
                <svg class="h-4 w-4 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
            </RouterLink>
            <div>
                <h1 class="text-2xl font-semibold text-white">Deployment Logs</h1>
                <p class="mt-1 text-sm text-zinc-400">
                    <code class="text-zinc-500">{{ route.params.uuid }}</code>
                </p>
            </div>
        </div>

        <!-- Deployment info -->
        <div v-if="deployment" class="flex items-center gap-6 text-sm">
            <span :class="[statusClass(deployment.status), 'inline-flex items-center rounded-full px-3 py-1 text-sm font-medium capitalize']">
                {{ deployment.status }}
            </span>
            <span class="text-zinc-400">
                Started {{ formatDate(deployment.created_at) }}
            </span>
            <span v-if="deployment.commit" class="text-zinc-400">
                Commit: <code class="text-zinc-300 bg-zinc-800 px-1.5 py-0.5 rounded">{{ deployment.commit?.substring(0, 7) }}</code>
            </span>
        </div>

        <!-- Log viewer -->
        <div class="rounded-xl border border-zinc-800 bg-zinc-900 overflow-hidden">
            <div class="border-b border-zinc-800 px-4 py-3 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="text-sm font-medium text-white">Build Output</span>
                    <span v-if="logs.length" class="text-xs text-zinc-500">{{ logs.length }} lines</span>
                </div>
                <div v-if="deployment?.status === 'in_progress'" class="flex items-center gap-2 text-sm text-amber-400">
                    <div class="h-2 w-2 rounded-full bg-amber-400 animate-pulse"></div>
                    Building...
                </div>
            </div>
            <div class="max-h-[600px] overflow-auto">
                <div v-if="logsLoading" class="flex items-center justify-center py-12">
                    <div class="h-6 w-6 animate-spin rounded-full border-2 border-zinc-700 border-t-violet-500"></div>
                </div>
                <div v-else-if="logs.length" class="font-mono text-xs">
                    <div
                        v-for="(line, index) in logs"
                        :key="index"
                        class="px-4 py-0.5 hover:bg-zinc-800 border-l-2 flex"
                        :class="{
                            'border-transparent': line.type === 'stdout',
                            'border-red-500 bg-red-500/5': line.type === 'stderr',
                            'border-amber-500 bg-amber-500/5': line.type === 'warning'
                        }"
                    >
                        <span class="text-zinc-600 select-none w-12 flex-shrink-0 text-right pr-4">{{ index + 1 }}</span>
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
                <div v-else class="px-4 py-12 text-center text-zinc-500">
                    No logs available for this deployment
                </div>
            </div>
        </div>
    </div>
</template>
