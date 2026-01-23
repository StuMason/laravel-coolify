<script setup>
import { ref, computed, inject, watch } from 'vue';

const stats = inject('stats');
const refreshStats = inject('refreshStats');
const selectedEnvironment = inject('selectedEnvironment');
const setSelectedEnvironment = inject('setSelectedEnvironment');

const isOpen = ref(false);

const environments = computed(() => stats.value?.environments || []);
const currentEnv = computed(() => stats.value?.environment);

function selectEnvironment(env) {
    setSelectedEnvironment(env.name);
    isOpen.value = false;
    refreshStats();
}
</script>

<template>
    <div class="relative">
        <button
            @click="isOpen = !isOpen"
            class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-zinc-800 transition-colors"
            :class="{ 'bg-zinc-800': isOpen }"
        >
            <span class="flex h-2 w-2 rounded-full" :class="currentEnv ? 'bg-emerald-500' : 'bg-zinc-500'"></span>
            <span class="flex-1 text-left text-white truncate">{{ currentEnv?.name || 'No environment' }}</span>
            <svg
                class="h-4 w-4 text-zinc-400 transition-transform"
                :class="{ 'rotate-180': isOpen }"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <!-- Dropdown -->
        <div
            v-if="isOpen && environments.length > 1"
            class="absolute left-0 right-0 mt-1 rounded-lg border border-zinc-700 bg-zinc-900 shadow-lg z-50"
        >
            <div class="py-1">
                <button
                    v-for="env in environments"
                    :key="env.uuid"
                    @click="selectEnvironment(env)"
                    class="flex w-full items-center gap-2 px-3 py-2 text-sm text-left hover:bg-zinc-800 transition-colors"
                    :class="{ 'bg-zinc-800': currentEnv?.uuid === env.uuid }"
                >
                    <span
                        class="flex h-2 w-2 rounded-full"
                        :class="currentEnv?.uuid === env.uuid ? 'bg-emerald-500' : 'bg-zinc-600'"
                    ></span>
                    <span class="text-white">{{ env.name }}</span>
                </button>
            </div>
        </div>

        <!-- Click outside to close -->
        <div v-if="isOpen" class="fixed inset-0 z-40" @click="isOpen = false"></div>
    </div>
</template>
