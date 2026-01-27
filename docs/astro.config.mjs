// @ts-check
import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';
import starlightClientMermaid from '@pasqal-io/starlight-client-mermaid';

// https://astro.build/config
export default defineConfig({
	site: 'https://stumason.github.io',
	base: '/laravel-coolify',
	integrations: [
		starlight({
			title: 'Laravel Coolify',
			description: 'Deploy Laravel apps to Coolify with zero-config provisioning',
			plugins: [starlightClientMermaid()],
			social: [
				{ icon: 'github', label: 'GitHub', href: 'https://github.com/StuMason/laravel-coolify' },
			],
			editLink: {
				baseUrl: 'https://github.com/StuMason/laravel-coolify/edit/main/docs/',
			},
			customCss: ['./src/styles/custom.css'],
			sidebar: [
				{
					label: 'Getting Started',
					items: [
						{ label: 'Introduction', slug: 'getting-started/introduction' },
						{ label: 'Installation', slug: 'getting-started/installation' },
						{ label: 'Configuration', slug: 'getting-started/configuration' },
						{ label: 'First Deployment', slug: 'getting-started/first-deployment' },
					],
				},
				{
					label: 'The Stack',
					items: [
						{ label: 'Overview', slug: 'stack/overview' },
						{ label: 'Dockerfile', slug: 'stack/dockerfile' },
						{ label: 'Supervisor', slug: 'stack/supervisor' },
						{ label: 'Nginx', slug: 'stack/nginx' },
						{ label: 'PHP Config', slug: 'stack/php-config' },
					],
				},
				{
					label: 'Architecture',
					items: [
						{ label: 'Why Coolify?', slug: 'architecture/why-coolify' },
						{ label: 'Postgres + Dragonfly', slug: 'architecture/postgres-dragonfly' },
						{ label: 'Horizon Queues', slug: 'architecture/horizon-queues' },
						{ label: 'Reverb WebSockets', slug: 'architecture/reverb-websockets' },
					],
				},
				{
					label: 'Commands',
					items: [
						{ label: 'coolify:install', slug: 'commands/install' },
						{ label: 'coolify:provision', slug: 'commands/provision' },
						{ label: 'coolify:deploy', slug: 'commands/deploy' },
						{ label: 'coolify:status', slug: 'commands/status' },
						{ label: 'coolify:logs', slug: 'commands/logs' },
						{ label: 'coolify:restart', slug: 'commands/restart' },
						{ label: 'coolify:rollback', slug: 'commands/rollback' },
						{ label: 'coolify:setup-ci', slug: 'commands/setup-ci' },
						{ label: 'coolify:destroy', slug: 'commands/destroy' },
					],
				},
				{
					label: 'Dashboard',
					items: [
						{ label: 'Overview', slug: 'dashboard/overview' },
						{ label: 'Authentication', slug: 'dashboard/authentication' },
						{ label: 'Kick Integration', slug: 'dashboard/kick' },
						{ label: 'API', slug: 'dashboard/api' },
					],
				},
				{
					label: 'Advanced',
					items: [
						{ label: 'Customization', slug: 'advanced/customization' },
						{ label: 'Multiple Apps', slug: 'advanced/multi-app' },
						{ label: 'CI/CD Integration', slug: 'advanced/ci-cd' },
					],
				},
				{
					label: 'Reference',
					items: [
						{ label: 'Configuration', slug: 'reference/config' },
						{ label: 'Environment Variables', slug: 'reference/env-vars' },
					],
				},
			],
		}),
	],
});
