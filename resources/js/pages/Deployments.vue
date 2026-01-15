<script setup>
import { inject, computed } from 'vue';
import { RouterLink } from 'vue-router';

const stats = inject('stats');

const deployments = computed(() => stats.value?.recentDeployments || []);

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

function statusClass(status) {
    if (status === 'finished') return 'bg-emerald-500/10 text-emerald-400';
    if (status === 'failed') return 'bg-red-500/10 text-red-400';
    if (status === 'in_progress') return 'bg-amber-500/10 text-amber-400 animate-pulse-status';
    if (status === 'queued') return 'bg-blue-500/10 text-blue-400';
    return 'bg-zinc-500/10 text-zinc-400';
}
</script>

<template>
    <div class="p-6 space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-white">Deployments</h1>
            <p class="mt-1 text-sm text-zinc-400">History of all deployments</p>
        </div>

        <div class="rounded-xl border border-zinc-800 bg-zinc-900 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-zinc-800 text-left text-sm text-zinc-400">
                        <th class="px-5 py-3 font-medium">Status</th>
                        <th class="px-5 py-3 font-medium">Commit</th>
                        <th class="px-5 py-3 font-medium">Duration</th>
                        <th class="px-5 py-3 font-medium">Started</th>
                        <th class="px-5 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800">
                    <tr
                        v-for="deployment in deployments"
                        :key="deployment.uuid"
                        class="hover:bg-zinc-800/50 transition-colors"
                    >
                        <td class="px-5 py-4">
                            <span :class="[statusClass(deployment.status), 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize']">
                                {{ deployment.status }}
                            </span>
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <code class="text-xs text-zinc-500 bg-zinc-800 px-1.5 py-0.5 rounded">{{ deployment.commit?.substring(0, 7) || '-' }}</code>
                                <span class="text-sm text-white truncate max-w-md">{{ deployment.commit_message || 'No commit message' }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-4 text-sm text-zinc-400">{{ formatDuration(deployment.duration) }}</td>
                        <td class="px-5 py-4 text-sm text-zinc-400">{{ formatDate(deployment.created_at) }}</td>
                        <td class="px-5 py-4 text-right">
                            <RouterLink
                                :to="`/deployments/${deployment.uuid}`"
                                class="text-sm text-violet-400 hover:text-violet-300 transition-colors"
                            >
                                View logs
                            </RouterLink>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-if="!deployments.length" class="px-5 py-12 text-center text-sm text-zinc-500">
                No deployments yet
            </div>
        </div>
    </div>
</template>
