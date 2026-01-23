import '../css/app.css';
import { createApp, ref, provide, readonly } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import Layout from './components/Layout.vue';
import api from './api.js';

// Get config from window
const basePath = window.Coolify?.path || '/coolify';
const pollInterval = window.Coolify?.pollInterval || 5000;

// Global state
const stats = ref(null);
const loading = ref(true);
const error = ref(null);

// Environment state - persisted in localStorage
const STORAGE_KEY = 'coolify_environment';
const selectedEnvironment = ref(localStorage.getItem(STORAGE_KEY) || 'production');

function setSelectedEnvironment(envName) {
    selectedEnvironment.value = envName;
    localStorage.setItem(STORAGE_KEY, envName);
}

// Fetch stats
async function fetchStats() {
    try {
        stats.value = await api.getStats(selectedEnvironment.value);
        error.value = null;
    } catch (e) {
        error.value = e.message;
        console.error('Failed to fetch stats:', e);
    } finally {
        loading.value = false;
    }
}

// Routes
const routes = [
    {
        path: '/',
        redirect: '/dashboard',
    },
    {
        path: '/dashboard',
        name: 'dashboard',
        component: () => import('./pages/Dashboard.vue'),
    },
    {
        path: '/deployments',
        name: 'deployments',
        component: () => import('./pages/Deployments.vue'),
    },
    {
        path: '/deployments/:uuid',
        name: 'deployment',
        component: () => import('./pages/DeploymentDetail.vue'),
        props: true,
    },
    {
        path: '/configuration',
        name: 'configuration',
        component: () => import('./pages/Configuration.vue'),
    },
    {
        path: '/logs',
        name: 'logs',
        component: () => import('./pages/Logs.vue'),
    },
    {
        path: '/resources',
        name: 'resources',
        component: () => import('./pages/Resources.vue'),
    },
    { path: '/environment', redirect: '/configuration?tab=environment' },
    { path: '/backups', redirect: '/configuration?tab=backups' },
    { path: '/settings', redirect: '/configuration?tab=settings' },
];

// Create router
const router = createRouter({
    history: createWebHistory(basePath),
    routes,
});

// Create app
const app = createApp(Layout);

// Provide global state
app.provide('stats', readonly(stats));
app.provide('loading', readonly(loading));
app.provide('error', readonly(error));
app.provide('refreshStats', fetchStats);
app.provide('api', api);
app.provide('basePath', basePath);
app.provide('selectedEnvironment', readonly(selectedEnvironment));
app.provide('setSelectedEnvironment', setSelectedEnvironment);

// Use router
app.use(router);

// Mount
app.mount('#app');

// Initial fetch
fetchStats();

// Start polling
if (pollInterval > 0) {
    setInterval(fetchStats, pollInterval);
}
