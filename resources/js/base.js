/**
 * Base mixin with shared utilities for all components
 */
export default {
    methods: {
        /**
         * Get status text color class
         */
        statusClass(status) {
            const s = (status || '').toLowerCase();
            if (s === 'running' || s === 'finished' || s === 'success' || s.includes('healthy')) {
                return 'text-green-400';
            }
            if (s === 'stopped' || s === 'exited' || s === 'failed' || s === 'error') {
                return 'text-red-400';
            }
            if (s === 'deploying' || s === 'starting' || s === 'restarting' || s === 'in_progress' || s === 'queued') {
                return 'text-blue-400';
            }
            return 'text-gray-400';
        },

        /**
         * Get status background class
         */
        statusBgClass(status) {
            const s = (status || '').toLowerCase();
            if (s === 'running' || s === 'finished' || s === 'success' || s.includes('healthy')) {
                return 'bg-green-900/30';
            }
            if (s === 'stopped' || s === 'exited' || s === 'failed' || s === 'error') {
                return 'bg-red-900/30';
            }
            if (s === 'deploying' || s === 'starting' || s === 'restarting' || s === 'in_progress' || s === 'queued') {
                return 'bg-blue-900/30';
            }
            return 'bg-gray-900/30';
        },

        /**
         * Get status dot class
         */
        statusDotClass(status) {
            const s = (status || '').toLowerCase();
            if (s === 'running' || s === 'finished' || s === 'success' || s.includes('healthy')) {
                return 'bg-green-500';
            }
            if (s === 'stopped' || s === 'exited' || s === 'failed' || s === 'error') {
                return 'bg-red-500';
            }
            if (s === 'deploying' || s === 'starting' || s === 'restarting' || s === 'in_progress' || s === 'queued') {
                return 'bg-blue-500 status-pulse';
            }
            return 'bg-gray-500';
        },

        /**
         * Get status badge class
         */
        statusBadgeClass(status) {
            const s = (status || '').toLowerCase();
            if (s === 'running' || s === 'finished' || s === 'success' || s.includes('healthy')) {
                return 'bg-green-900/50 text-green-400';
            }
            if (s === 'stopped' || s === 'exited' || s === 'failed' || s === 'error') {
                return 'bg-red-900/50 text-red-400';
            }
            if (s === 'deploying' || s === 'starting' || s === 'restarting' || s === 'in_progress' || s === 'queued') {
                return 'bg-blue-900/50 text-blue-400';
            }
            return 'bg-gray-900/50 text-gray-400';
        },

        /**
         * Format date as relative time
         */
        formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            const now = new Date();
            const diff = now - date;

            if (diff < 60000) return 'Just now';
            if (diff < 3600000) return Math.floor(diff / 60000) + 'm ago';
            if (diff < 86400000) return Math.floor(diff / 3600000) + 'h ago';
            if (diff < 604800000) return Math.floor(diff / 86400000) + 'd ago';
            return date.toLocaleDateString();
        },

        /**
         * Format duration between two dates
         */
        formatDuration(start, end) {
            if (!start || !end) return '';
            const diff = new Date(end) - new Date(start);
            const seconds = Math.floor(diff / 1000);
            if (seconds < 60) return seconds + 's';
            const minutes = Math.floor(seconds / 60);
            const remainingSeconds = seconds % 60;
            return minutes + 'm ' + remainingSeconds + 's';
        },

        /**
         * Copy text to clipboard
         */
        async copyToClipboard(text) {
            if (!text) return;
            try {
                if (navigator.clipboard?.writeText) {
                    await navigator.clipboard.writeText(text);
                } else {
                    const textarea = document.createElement('textarea');
                    textarea.value = text;
                    textarea.style.position = 'fixed';
                    textarea.style.opacity = '0';
                    document.body.appendChild(textarea);
                    textarea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textarea);
                }
                this.$root.alert('Copied to clipboard', 'success');
            } catch (err) {
                console.error('Failed to copy:', err);
            }
        },

        /**
         * Extract repo display name from git URL
         */
        getRepoDisplayName(repo) {
            if (!repo) return '';
            if (repo.startsWith('git@')) {
                return repo.replace(/^git@github\.com:/, '').replace(/\.git$/, '');
            }
            if (repo.includes('github.com/')) {
                return repo.replace(/^https?:\/\/github\.com\//, '').replace(/\.git$/, '');
            }
            return repo;
        },

        /**
         * Get GitHub deploy keys URL for a repo
         */
        getGitHubKeysUrl(repo) {
            const repoName = this.getRepoDisplayName(repo);
            return repoName ? `https://github.com/${repoName}/settings/keys` : '#';
        },

        /**
         * Escape HTML entities
         */
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
    },
};
