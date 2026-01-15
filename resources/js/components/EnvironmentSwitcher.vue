<script setup>
import { ref, computed, onMounted, inject } from 'vue';

const api = inject('api');
const refreshStats = inject('refreshStats');
const toast = inject('toast');

const environments = ref([]);
const loading = ref(true);
const switching = ref(false);
const isOpen = ref(false);

const currentEnv = computed(() => environments.value.find(e => e.is_default));
const otherEnvs = computed(() => environments.value.filter(e => !e.is_default));
const hasMultipleEnvs = computed(() => environments.value.length > 1);

async function fetchEnvironments() {
    try {
        environments.value = await api.getEnvironments();
    } catch (e) {
        console.error('Failed to fetch environments:', e);
    } finally {
        loading.value = false;
    }
}

async function switchEnv(env) {
    if (switching.value || env.is_default) return;
    switching.value = true;
    isOpen.value = false;

    try {
        await api.switchEnvironment(env.id);
        toast.value?.success('Environment Switched', `Now viewing ${env.name}`);
        await fetchEnvironments();
        await refreshStats();
    } catch (e) {
        toast.value?.error('Switch Failed', e.message);
    } finally {
        switching.value = false;
    }
}

function toggleDropdown() {
    if (hasMultipleEnvs.value) {
        isOpen.value = !isOpen.value;
    }
}

function closeDropdown(e) {
    if (!e.target.closest('.env-switcher')) {
        isOpen.value = false;
    }
}

onMounted(() => {
    fetchEnvironments();
    document.addEventListener('click', closeDropdown);
});
</script>

<template>
    <div class="env-switcher relative" v-if="!loading">
        <!-- Current environment button -->
        <button
            @click="toggleDropdown"
            :class="[
                'flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm transition-colors',
                hasMultipleEnvs ? 'hover:bg-zinc-800 cursor-pointer' : 'cursor-default',
                isOpen ? 'bg-zinc-800' : ''
            ]"
        >
            <span class="flex h-2 w-2 rounded-full bg-emerald-500" v-if="currentEnv"></span>
            <span class="flex-1 text-left text-white truncate">{{ currentEnv?.name || 'No environment' }}</span>
            <span v-if="currentEnv?.environment" class="text-xs text-zinc-500">{{ currentEnv.environment }}</span>
            <svg v-if="hasMultipleEnvs" class="h-4 w-4 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>
        </button>

        <!-- Dropdown -->
        <Transition
            enter-active-class="transition duration-100 ease-out"
            enter-from-class="transform scale-95 opacity-0"
            enter-to-class="transform scale-100 opacity-100"
            leave-active-class="transition duration-75 ease-in"
            leave-from-class="transform scale-100 opacity-100"
            leave-to-class="transform scale-95 opacity-0"
        >
            <div
                v-if="isOpen && hasMultipleEnvs"
                class="absolute left-0 right-0 top-full z-10 mt-1 rounded-lg border border-zinc-700 bg-zinc-800 py-1 shadow-xl"
            >
                <div class="px-3 py-2 text-xs font-medium text-zinc-500 uppercase tracking-wide">
                    Switch Environment
                </div>
                <button
                    v-for="env in otherEnvs"
                    :key="env.id"
                    @click="switchEnv(env)"
                    :disabled="switching"
                    class="flex w-full items-center gap-2 px-3 py-2 text-sm text-zinc-300 hover:bg-zinc-700 hover:text-white disabled:opacity-50 transition-colors"
                >
                    <span class="flex h-2 w-2 rounded-full bg-zinc-500"></span>
                    <span class="flex-1 text-left truncate">{{ env.name }}</span>
                    <span v-if="env.environment" class="text-xs text-zinc-500">{{ env.environment }}</span>
                </button>
                <div v-if="!otherEnvs.length" class="px-3 py-2 text-sm text-zinc-500">
                    No other environments
                </div>
            </div>
        </Transition>
    </div>

    <!-- Loading state -->
    <div v-else class="flex items-center gap-2 px-3 py-2">
        <div class="h-4 w-4 animate-spin rounded-full border-2 border-zinc-700 border-t-violet-500"></div>
        <span class="text-sm text-zinc-400">Loading...</span>
    </div>
</template>
