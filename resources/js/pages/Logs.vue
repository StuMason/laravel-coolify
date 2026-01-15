<script setup>
import { ref, onMounted, onUnmounted, inject, computed } from 'vue';

const stats = inject('stats');
const api = inject('api');

const logs = ref('');
const loading = ref(true);
const autoScroll = ref(true);

const appUuid = computed(() => stats.value?.application?.uuid);

let pollInterval = null;
let logContainer = null;

async function fetchLogs() {
    if (!appUuid.value) return;
    try {
        const result = await api.getApplicationLogs(appUuid.value);
        logs.value = result.logs || '';
        if (autoScroll.value && logContainer) {
            setTimeout(() => {
                logContainer.scrollTop = logContainer.scrollHeight;
            }, 0);
        }
    } catch (e) {
        console.error('Failed to fetch logs:', e);
    } finally {
        loading.value = false;
    }
}

function setLogContainer(el) {
    logContainer = el;
}

onMounted(() => {
    fetchLogs();
    pollInterval = setInterval(fetchLogs, 5000);
});

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
});
</script>

<template>
    <div class="p-6 space-y-6 h-full flex flex-col">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-white">Application Logs</h1>
                <p class="mt-1 text-sm text-zinc-400">Live application output</p>
            </div>
            <label class="flex items-center gap-2 text-sm text-zinc-400">
                <input
                    v-model="autoScroll"
                    type="checkbox"
                    class="h-4 w-4 rounded border-zinc-700 bg-zinc-800 text-violet-600 focus:ring-violet-500 focus:ring-offset-zinc-900"
                />
                Auto-scroll
            </label>
        </div>

        <div class="flex-1 min-h-0 rounded-xl border border-zinc-800 bg-zinc-900 overflow-hidden">
            <div v-if="loading" class="flex items-center justify-center h-full">
                <div class="h-6 w-6 animate-spin rounded-full border-2 border-zinc-700 border-t-violet-500"></div>
            </div>
            <div
                v-else
                :ref="setLogContainer"
                class="h-full overflow-auto p-4"
            >
                <pre class="font-mono text-sm text-zinc-300 whitespace-pre-wrap">{{ logs || 'No logs available' }}</pre>
            </div>
        </div>
    </div>
</template>
