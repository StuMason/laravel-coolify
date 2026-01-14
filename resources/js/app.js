import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import axios from 'axios';
import routes from '@/routes.js';
import base from '@/base.js';

// Configure axios
const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}
axios.defaults.headers.common['Accept'] = 'application/json';
axios.defaults.headers.common['Content-Type'] = 'application/json';

// Get base path from the window (set by blade template)
const basePath = window.Coolify?.path || '/coolify';

// Create router
const router = createRouter({
    history: createWebHistory(basePath),
    routes,
});

// Create app
const app = createApp({
    data() {
        return {
            // Global app state
            stats: null,
            loading: true,
            alertMessage: null,
            alertType: 'info',
            alertTimeout: null,
        };
    },

    mounted() {
        this.fetchStats();
        // Poll for updates
        const pollInterval = window.Coolify?.pollInterval || 10000;
        if (pollInterval > 0) {
            setInterval(() => this.fetchStats(), pollInterval);
        }
    },

    methods: {
        async fetchStats() {
            try {
                const response = await axios.get(`${basePath}/api/stats`);
                this.stats = response.data;
                this.loading = false;
            } catch (error) {
                console.error('Failed to fetch stats:', error);
                this.loading = false;
            }
        },

        alert(message, type = 'info', duration = 3000) {
            this.alertMessage = message;
            this.alertType = type;
            if (this.alertTimeout) {
                clearTimeout(this.alertTimeout);
            }
            if (duration > 0) {
                this.alertTimeout = setTimeout(() => {
                    this.alertMessage = null;
                }, duration);
            }
        },

        clearAlert() {
            this.alertMessage = null;
            if (this.alertTimeout) {
                clearTimeout(this.alertTimeout);
            }
        },
    },
});

// Global properties
app.config.globalProperties.$http = axios;
app.config.globalProperties.$basePath = basePath;

// Register base mixin
app.mixin(base);

// Use router
app.use(router);

// Mount
app.mount('#coolify');
