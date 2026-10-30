<script setup>
import { ref, computed, onMounted } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import Avatar from 'primevue/avatar';
import Badge from 'primevue/badge';
import Button from 'primevue/button';
import Menu from 'primevue/menu';
import OverlayBadge from 'primevue/overlaybadge';

const props = defineProps({
    title: {
        type: String,
        default: 'Dashboard E-SDM',
    },
    breadcrumbs: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();

// Mobile sidebar state
const mobileSidebarOpen = ref(false);
const toggleMobileSidebar = () => {
    mobileSidebarOpen.value = !mobileSidebarOpen.value;
};

// Accordion submenu state
const openGroups = ref({
    kepegawaian: true,
    layanan: true,
    master: false,
});

const toggleGroup = (key) => {
    openGroups.value[key] = !openGroups.value[key];
};

// User Profile Menu
const userMenu = ref(null);
const toggleUserMenu = (event) => {
    userMenu.value.toggle(event);
};

const userMenuItems = [
    {
        label: 'Profil Saya',
        icon: 'pi pi-user',
        command: () => {
            // Bisa diarahkan ke profil
        },
    },
    {
        label: 'Keamanan Akun',
        icon: 'pi pi-shield',
        command: () => {},
    },
    {
        separator: true,
    },
    {
        label: 'Keluar',
        icon: 'pi pi-sign-out',
        class: 'text-rose-600',
        command: () => {
            // Logout handler
            router.post('/logout', {}, {
                onError: () => {
                    // fallback reload
                    window.location.href = '/';
                }
            });
        },
    },
];

// Notification Popover state
const notificationMenu = ref(null);
const toggleNotificationMenu = (event) => {
    notificationMenu.value.toggle(event);
};

const notifications = ref([
    {
        id: 1,
        title: 'Pengajuan Cuti Tahunan',
        user: 'Budi Santoso (Div. IT)',
        time: '10 menit yang lalu',
        type: 'cuti',
        icon: 'pi pi-calendar-plus text-indigo-600 bg-indigo-50',
    },
    {
        id: 2,
        title: 'Klaim Lembur Proyek',
        user: 'Siti Rahma (Div. Keuangan)',
        time: '35 menit yang lalu',
        type: 'lembur',
        icon: 'pi pi-clock text-amber-600 bg-amber-50',
    },
    {
        id: 3,
        title: 'Izin Sakit Terlampir Surat',
        user: 'Ahmad Fauzi (Div. Umum)',
        time: '2 jam yang lalu',
        type: 'izin',
        icon: 'pi pi-file-edit text-emerald-600 bg-emerald-50',
    },
]);

// Helper checking active route
const isCurrentUrl = (path) => {
    if (!path) return false;
    const current = page.url.split('?')[0];
    if (path === '/' && current === '/') return true;
    return path !== '/' && current.startsWith(path);
};

// Auto-expand accordion if child route is active
onMounted(() => {
    const current = page.url;
    if (current.includes('/pegawai') || current.includes('/jabatan') || current.includes('/unit-kerja')) {
        openGroups.value.kepegawaian = true;
    }
    if (current.includes('/presensi') || current.includes('/cuti') || current.includes('/lembur') || current.includes('/datatable')) {
        openGroups.value.layanan = true;
    }
});
</script>

<template>
    <div class="min-h-screen bg-[#f5f5f9] text-slate-700 font-sans antialiased flex">

        <!-- Backdrop for mobile drawer -->
        <div
            v-if="mobileSidebarOpen"
            class="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-sm lg:hidden transition-opacity"
            @click="mobileSidebarOpen = false"
        />

        <!-- ================= SIDEBAR ================= -->
        <aside
            :class="[
                'fixed top-0 bottom-0 left-0 z-50 w-64 bg-white border-r border-slate-200/80 shadow-[0_2px_12px_rgba(67,89,113,0.06)] flex flex-col transition-transform duration-300 ease-in-out lg:translate-x-0',
                mobileSidebarOpen ? 'translate-x-0' : '-translate-x-full',
            ]"
        >
            <!-- Brand / Logo -->
            <div class="h-16 px-6 border-b border-slate-100 flex items-center justify-between shrink-0">
                <Link href="/" class="flex items-center gap-3 group">
                    <div
                        class="size-9 rounded-xl bg-gradient-to-tr from-[#696cff] to-[#8587ff] text-white flex items-center justify-center font-bold text-base shadow-[0_3px_8px_rgba(105,108,255,0.4)] group-hover:scale-105 transition-transform"
                    >
                        <i class="pi pi-id-card text-lg" />
                    </div>
                    <div class="flex flex-col">
                        <span class="font-bold text-slate-800 text-[15px] leading-tight tracking-tight">
                            E-SDM KFPB
                        </span>
                        <span class="text-[10px] font-medium text-slate-400 tracking-wider uppercase">
                            Enterprise Portal
                        </span>
                    </div>
                </Link>

                <!-- Mobile close button -->
                <button
                    class="lg:hidden p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100"
                    @click="mobileSidebarOpen = false"
                >
                    <i class="pi pi-times text-sm" />
                </button>
            </div>

            <!-- Navigation Links -->
            <nav class="flex-1 overflow-y-auto px-3.5 py-4 space-y-6 custom-scrollbar text-sm">

                <!-- Section: DASHBOARD -->
                <div>
                    <div class="px-3 mb-2 text-[10px] font-bold tracking-wider text-slate-400 uppercase">
                        Utama
                    </div>
                    <ul class="space-y-1">
                        <li>
                            <Link
                                href="/"
                                :class="[
                                    'flex items-center gap-3 px-3.5 py-2.5 rounded-lg transition-all duration-150 font-medium cursor-pointer',
                                    isCurrentUrl('/')
                                        ? 'bg-[#696cff] text-white shadow-[0_2px_8px_rgba(105,108,255,0.4)]'
                                        : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900',
                                ]"
                            >
                                <i :class="['pi pi-home text-base shrink-0', isCurrentUrl('/') ? 'text-white' : 'text-slate-400']" />
                                <span>Dashboard Presensi</span>
                            </Link>
                        </li>
                    </ul>
                </div>

                <!-- Section: KEPEGAWAIAN (Accordion) -->
                <div>
                    <div class="px-3 mb-2 text-[10px] font-bold tracking-wider text-slate-400 uppercase">
                        Modul SDM
                    </div>
                    <div class="space-y-1">
                        <!-- Group Header (Accordion trigger) -->
                        <button
                            type="button"
                            class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors duration-150 font-medium"
                            @click="toggleGroup('kepegawaian')"
                        >
                            <div class="flex items-center gap-3">
                                <i class="pi pi-users text-base text-slate-400" />
                                <span>Kepegawaian</span>
                            </div>
                            <i
                                :class="[
                                    'pi pi-chevron-right text-xs text-slate-400 transition-transform duration-200',
                                    openGroups.kepegawaian ? 'rotate-90' : '',
                                ]"
                            />
                        </button>

                        <!-- Submenu items -->
                        <ul v-show="openGroups.kepegawaian" class="pl-8 pr-1 space-y-1 pt-0.5">
                            <li>
                                <Link
                                    href="/datatable"
                                    :class="[
                                        'flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors',
                                        isCurrentUrl('/datatable')
                                            ? 'bg-[#696cff]/10 text-[#696cff] font-semibold'
                                            : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900',
                                    ]"
                                >
                                    <span class="size-1.5 rounded-full bg-current shrink-0" />
                                    <span>Direktori Pegawai</span>
                                </Link>
                            </li>
                            <li>
                                <Link
                                    href="/components"
                                    :class="[
                                        'flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors',
                                        isCurrentUrl('/components')
                                            ? 'bg-[#696cff]/10 text-[#696cff] font-semibold'
                                            : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900',
                                    ]"
                                >
                                    <span class="size-1.5 rounded-full bg-current shrink-0" />
                                    <span>Struktur & Jabatan</span>
                                </Link>
                            </li>
                        </ul>
                    </div>

                    <!-- Group: LAYANAN / TRANSAKSI SDM (Accordion) -->
                    <div class="space-y-1 mt-1">
                        <button
                            type="button"
                            class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors duration-150 font-medium"
                            @click="toggleGroup('layanan')"
                        >
                            <div class="flex items-center gap-3">
                                <i class="pi pi-calendar text-base text-slate-400" />
                                <span>Layanan & Izin</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="bg-amber-100 text-amber-700 text-[10px] font-bold px-1.5 py-0.5 rounded-full">
                                    3
                                </span>
                                <i
                                    :class="[
                                        'pi pi-chevron-right text-xs text-slate-400 transition-transform duration-200',
                                        openGroups.layanan ? 'rotate-90' : '',
                                    ]"
                                />
                            </div>
                        </button>

                        <ul v-show="openGroups.layanan" class="pl-8 pr-1 space-y-1 pt-0.5">
                            <li>
                                <Link
                                    href="/"
                                    :class="[
                                        'flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors',
                                        page.url === '/' ? 'text-slate-600 hover:bg-slate-100/80' : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900',
                                    ]"
                                >
                                    <span class="size-1.5 rounded-full bg-current shrink-0" />
                                    <span>Presensi Harian</span>
                                </Link>
                            </li>
                            <li>
                                <Link
                                    href="/forms"
                                    :class="[
                                        'flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors',
                                        isCurrentUrl('/forms')
                                            ? 'bg-[#696cff]/10 text-[#696cff] font-semibold'
                                            : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900',
                                    ]"
                                >
                                    <span class="size-1.5 rounded-full bg-current shrink-0" />
                                    <span>Pengajuan Cuti / Izin</span>
                                </Link>
                            </li>
                            <li>
                                <Link
                                    href="/charts"
                                    :class="[
                                        'flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors',
                                        isCurrentUrl('/charts')
                                            ? 'bg-[#696cff]/10 text-[#696cff] font-semibold'
                                            : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900',
                                    ]"
                                >
                                    <span class="size-1.5 rounded-full bg-current shrink-0" />
                                    <span>Rekap Lembur & KPI</span>
                                </Link>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Section: SISTEM -->
                <div>
                    <div class="px-3 mb-2 text-[10px] font-bold tracking-wider text-slate-400 uppercase">
                        Sistem & Pengaturan
                    </div>
                    <ul class="space-y-1">
                        <li>
                            <Link
                                href="/components"
                                :class="[
                                    'flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 font-medium transition-colors',
                                ]"
                            >
                                <i class="pi pi-sliders-h text-base text-slate-400" />
                                <span>Konfigurasi Shift</span>
                            </Link>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Sidebar User Quick Footer -->
            <div class="p-3.5 border-t border-slate-100 bg-slate-50/60">
                <div class="flex items-center gap-3 p-2 rounded-xl bg-white border border-slate-200/60 shadow-xs">
                    <Avatar
                        label="AD"
                        shape="circle"
                        class="!bg-[#696cff]/15 !text-[#696cff] font-semibold text-xs shrink-0 size-9"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold text-slate-800 truncate">Admin SDM</p>
                        <p class="text-[11px] text-slate-400 truncate">HR Management</p>
                    </div>
                    <span class="size-2 rounded-full bg-emerald-500 shrink-0" title="Online" />
                </div>
            </div>
        </aside>

        <!-- ================= MAIN WRAPPER ================= -->
        <div class="flex-1 flex flex-col min-w-0 lg:pl-64 transition-all">

            <!-- ================= FLOATING TOP NAVBAR ================= -->
            <header class="sticky top-3 z-30 mx-4 sm:mx-6 mt-3 mb-2">
                <div
                    class="bg-white/85 backdrop-blur-md rounded-xl border border-slate-200/70 shadow-[0_2px_6px_0_rgba(67,89,113,0.06)] px-4 py-2.5 flex items-center justify-between"
                >
                    <!-- Left: Mobile Toggle & Page Title / Breadcrumbs -->
                    <div class="flex items-center gap-3 min-w-0">
                        <button
                            type="button"
                            class="lg:hidden p-2 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700"
                            @click="toggleMobileSidebar"
                        >
                            <i class="pi pi-bars text-lg" />
                        </button>

                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5 text-xs text-slate-400">
                                <Link href="/" class="hover:text-slate-600 transition-colors">E-SDM</Link>
                                <i class="pi pi-angle-right text-[10px]" />
                                <span class="text-slate-600 font-medium truncate">{{ title }}</span>
                            </div>
                            <h1 class="text-sm font-bold text-slate-800 tracking-tight truncate hidden sm:block">
                                {{ title }}
                            </h1>
                        </div>
                    </div>

                    <!-- Right: Search, Notifications & Avatar Dropdown -->
                    <div class="flex items-center gap-2 sm:gap-3">

                        <!-- Quick Search button -->
                        <div class="hidden md:flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-100/80 text-slate-400 text-xs border border-slate-200/50">
                            <i class="pi pi-search text-xs" />
                            <span class="text-slate-500">Cari pegawai / data...</span>
                            <kbd class="ml-2 font-mono text-[10px] bg-white px-1.5 py-0.5 rounded border border-slate-200 text-slate-400">⌘K</kbd>
                        </div>

                        <!-- Notification Button (Pending Approval) -->
                        <div class="relative">
                            <button
                                type="button"
                                class="relative p-2 rounded-xl text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors"
                                @click="toggleNotificationMenu"
                            >
                                <i class="pi pi-bell text-lg" />
                                <span
                                    class="absolute top-1 right-1 size-2 rounded-full bg-rose-500 ring-2 ring-white animate-pulse"
                                />
                            </button>

                            <!-- Notification Dropdown Menu Popover -->
                            <div
                                v-if="notificationMenu"
                                class="hidden"
                            />
                        </div>

                        <!-- User Profile Dropdown Button -->
                        <div class="flex items-center pl-2 border-l border-slate-200/80">
                            <button
                                type="button"
                                class="flex items-center gap-2.5 p-1 rounded-xl hover:bg-slate-100 transition-colors text-left"
                                @click="toggleUserMenu"
                                aria-haspopup="true"
                                aria-controls="user_menu"
                            >
                                <Avatar
                                    label="AD"
                                    shape="circle"
                                    class="!bg-[#696cff] !text-white font-semibold text-xs size-8 shadow-xs"
                                />
                                <div class="hidden sm:block text-left">
                                    <div class="text-xs font-bold text-slate-800 leading-tight">Admin SDM</div>
                                    <div class="text-[10px] font-medium text-slate-400 leading-tight">Super Admin</div>
                                </div>
                                <i class="pi pi-chevron-down text-[10px] text-slate-400 hidden sm:block ml-0.5" />
                            </button>

                            <Menu ref="userMenu" id="user_menu" :model="userMenuItems" :popup="true" class="!rounded-xl !shadow-lg !border-slate-200 text-xs" />
                        </div>
                    </div>
                </div>
            </header>

            <!-- ================= MAIN BODY CONTENT ================= -->
            <main class="flex-1 px-4 sm:px-6 py-4">
                <slot />
            </main>

            <!-- ================= CLEAN FOOTER ================= -->
            <footer class="px-6 py-4 border-t border-slate-200/60 text-xs text-slate-400 flex flex-col sm:flex-row items-center justify-between gap-2 mt-auto">
                <div class="flex items-center gap-1">
                    <span>© 2026</span>
                    <span class="font-semibold text-slate-600">E-SDM KFPB</span>
                    <span>— Divisi Sumber Daya Manusia & Organisasi</span>
                </div>
                <div class="flex gap-4">
                    <a href="#" class="hover:text-slate-600">Bantuan</a>
                    <a href="#" class="hover:text-slate-600">Kebijakan Privasi</a>
                    <a href="#" class="hover:text-slate-600">v2.1-stable</a>
                </div>
            </footer>
        </div>
    </div>
</template>

<style scoped>
/* Custom slim scrollbar ala modern dashboard */
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #cbd5e1;
}
</style>

