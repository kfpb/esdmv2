<script setup>
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Avatar from 'primevue/avatar';
import OverlayBadge from 'primevue/overlaybadge';
import Badge from 'primevue/badge';

const sidebarCollapsed = ref(false);
const page = usePage();

const menuItems = [
    { label: 'Dashboard',  icon: 'pi pi-home',       route: '/'          },
    { label: 'Components', icon: 'pi pi-th-large',   route: '/components'},
    { label: 'Data Table', icon: 'pi pi-table',       route: '/datatable' },
    { label: 'Forms',      icon: 'pi pi-file-edit',   route: '/forms'     },
    { label: 'Charts',     icon: 'pi pi-chart-bar',   route: '/charts'    },
];

const isActive = (route) => page.url === route || page.url.startsWith(route + '/');

const toggleSidebar = () => {
    sidebarCollapsed.value = !sidebarCollapsed.value;
};
</script>

<template>
    <div class="flex h-screen bg-surface-ground overflow-hidden">

        <!-- Sidebar -->
        <aside
            :class="[
                'flex flex-col bg-surface-card border-r border-surface-border transition-all duration-300 ease-in-out z-20',
                sidebarCollapsed ? 'w-16' : 'w-64',
            ]"
        >
            <!-- Logo -->
            <div class="flex items-center gap-3 px-4 py-4 border-b border-surface-border min-h-16 overflow-hidden">
                <div class="flex items-center justify-center size-8 rounded-lg bg-primary text-white font-bold text-sm shrink-0">
                    e
                </div>
                <span v-if="!sidebarCollapsed" class="font-semibold text-surface-900 whitespace-nowrap text-base truncate">
                    eSDMv2-KFPB
                </span>
            </div>

            <!-- Nav Items -->
            <nav class="flex-1 py-3 overflow-y-auto overflow-x-hidden">
                <ul class="space-y-0.5 px-2">
                    <li v-for="item in menuItems" :key="item.label">
                        <Link
                            :href="item.route"
                            :class="[
                                'flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors duration-150 cursor-pointer',
                                isActive(item.route)
                                    ? 'bg-primary text-white'
                                    : 'text-surface-600 hover:bg-surface-hover hover:text-surface-900',
                            ]"
                            v-tooltip.right="sidebarCollapsed ? item.label : null"
                        >
                            <i :class="[item.icon, 'text-base shrink-0']" />
                            <span v-if="!sidebarCollapsed" class="text-sm font-medium whitespace-nowrap">
                                {{ item.label }}
                            </span>
                        </Link>
                    </li>
                </ul>
            </nav>

            <!-- Sidebar Footer -->
            <div class="border-t border-surface-border p-2">
                <div
                    :class="[
                        'flex items-center gap-3 px-2 py-2 rounded-lg overflow-hidden',
                        sidebarCollapsed ? 'justify-center' : '',
                    ]"
                >
                    <Avatar
                        label="A"
                        class="shrink-0 !bg-primary !text-white"
                        shape="circle"
                    />
                    <div v-if="!sidebarCollapsed" class="min-w-0">
                        <p class="text-xs font-semibold text-surface-900 truncate">Admin User</p>
                        <p class="text-xs text-surface-500 truncate">admin@example.com</p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex flex-col flex-1 min-w-0">

            <!-- Topbar -->
            <header class="flex items-center justify-between px-6 bg-surface-card border-b border-surface-border min-h-16">
                <div class="flex items-center gap-3">
                    <Button
                        :icon="sidebarCollapsed ? 'pi pi-chevron-right' : 'pi pi-chevron-left'"
                        text
                        rounded
                        severity="secondary"
                        @click="toggleSidebar"
                        size="small"
                    />
                    <div class="h-5 w-px bg-surface-border" />
                    <slot name="header">
                        <span class="text-sm text-surface-600">Dashboard</span>
                    </slot>
                </div>

                <div class="flex items-center gap-2">
                    <!-- Bell with badge overlay -->
                    <OverlayBadge value="3" severity="warn">
                        <Button icon="pi pi-bell" text rounded severity="secondary" size="small" />
                    </OverlayBadge>
                    <Button icon="pi pi-cog" text rounded severity="secondary" size="small" />
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto p-6">
                <slot />
            </main>
        </div>
    </div>
</template>
