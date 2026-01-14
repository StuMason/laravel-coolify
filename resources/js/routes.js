export default [
    {
        path: '/',
        redirect: '/dashboard',
    },
    {
        path: '/dashboard',
        name: 'dashboard',
        component: () => import('@/screens/Dashboard.vue'),
    },
    {
        path: '/deployments',
        name: 'deployments',
        component: () => import('@/screens/Deployments.vue'),
    },
    {
        path: '/deployments/:id',
        name: 'deployment',
        component: () => import('@/screens/DeploymentDetail.vue'),
        props: true,
    },
    {
        path: '/logs',
        name: 'logs',
        component: () => import('@/screens/Logs.vue'),
    },
    {
        path: '/environment',
        name: 'environment',
        component: () => import('@/screens/Environment.vue'),
    },
    {
        path: '/settings',
        name: 'settings',
        component: () => import('@/screens/Settings.vue'),
    },
    {
        path: '/backups',
        name: 'backups',
        component: () => import('@/screens/Backups.vue'),
    },
    {
        path: '/resources',
        name: 'resources',
        component: () => import('@/screens/Resources.vue'),
    },
];
