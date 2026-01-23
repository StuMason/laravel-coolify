<script setup>
import { computed, inject } from 'vue';

const stats = inject('stats');

const currentEnv = computed(() => stats.value?.environment);
const projectName = computed(() => stats.value?.project?.name);
</script>

<template>
    <div class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm">
        <span class="flex h-2 w-2 rounded-full bg-emerald-500" v-if="currentEnv"></span>
        <span class="flex h-2 w-2 rounded-full bg-zinc-500" v-else></span>
        <span class="flex-1 text-left text-white truncate">{{ currentEnv?.name || 'No environment' }}</span>
    </div>
</template>
