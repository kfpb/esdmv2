<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import Avatar from 'primevue/avatar';
import Badge from 'primevue/badge';
import Button from 'primevue/button';
import Menu from 'primevue/menu';

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

// Current User from Inertia shared data or fallback preview
const currentUser = computed(() => {
    const authUser = page.props.auth?.user;
    if (authUser) {
        return {
            ...authUser,
            displayName: authUser.employee?.full_name || authUser.name || authUser.username || 'Pengguna SDM',
            positionName: authUser.employee?.position || (authUser.role === 'admin' ? 'Administrator SDM' : 'Staf Pegawai'),
            divisionName: authUser.employee?.division || 'PT Kimia Farma Plant Banjaran',
            avatarInitials: (authUser.employee?.full_name || authUser.name || authUser.username || 'SD')
                .split(' ')
                .map((n) => n[0])
                .slice(0, 2)
                .join('')
                .toUpperCase(),
        };
    }
    // Fallback aman jika pengguna belum terautentikasi
    return {
        id: null,
        name: '',
        username: '',
        employee_nip: '',
        role: 'guest',
        has_admin_access: false,
        displayName: 'Pengguna',
        positionName: '',
        divisionName: '',
        avatarInitials: 'KF',
    };
});

// Admin access check
const hasAdminAccess = computed(() => {
    return (
        currentUser.value.has_admin_access ||
        ['admin', 'superadmin', 'director', 'hrd', 'supervisor'].includes(currentUser.value.role)
    );
});

// Petugas Satpam / Keamanan check
const isSecurityGuard = computed(() => {
    const u = currentUser.value || {};
    const uname = (u.username || '').toUpperCase();
    const nip = (u.employee_nip || '').toUpperCase();
    return uname.startsWith('SC_') || nip.startsWith('SC_') || u.role === 'security' || hasAdminAccess.value;
});

// Portal View Mode: 'admin' (Manajemen/HRD) vs 'employee' (Layanan Mandiri Pegawai)
const portalMode = ref('admin');

onMounted(() => {
    const savedMode = localStorage.getItem('esdm_portal_mode');
    if (savedMode && (savedMode === 'employee' || hasAdminAccess.value)) {
        portalMode.value = savedMode;
    } else {
        portalMode.value = hasAdminAccess.value ? 'admin' : 'employee';
    }
});

const setPortalMode = (mode) => {
    if (mode === 'admin' && !hasAdminAccess.value) {
        return;
    }
    portalMode.value = mode;
    localStorage.setItem('esdm_portal_mode', mode);
};

// Mobile sidebar drawer state
const mobileSidebarOpen = ref(false);
const toggleMobileSidebar = () => {
    mobileSidebarOpen.value = !mobileSidebarOpen.value;
};

// Submenu Accordion state
const openGroups = ref({
    // Admin Groups
    masterData: false,
    kepegawaian: true,
    operasional: true,
    absensi: true,
    rekapitulasi: true,
    payroll: false,
    sistem: false,
    // Employee Groups
    layananMandiri: true,
    portofolioSaya: false,
});

const toggleGroup = (key) => {
    openGroups.value[key] = !openGroups.value[key];
};

// User Profile Menu
const userMenu = ref(null);
const toggleUserMenu = (event) => {
    userMenu.value.toggle(event);
};

const userMenuItems = computed(() => {
    const items = [
        {
            label: 'Profil Saya',
            icon: 'pi pi-user',
            command: () => {
                router.visit('/forms');
            },
        },
        {
            label: 'Slip Gaji Saya',
            icon: 'pi pi-receipt',
            command: () => {
                router.visit('/datatable');
            },
        },
        {
            label: 'Keamanan & Sandi',
            icon: 'pi pi-shield',
            command: () => {
                router.visit('/components');
            },
        },
    ];

    if (hasAdminAccess.value) {
        items.unshift({
            label: portalMode.value === 'admin' ? 'Beralih ke Portal Pegawai' : 'Beralih ke Portal Admin',
            icon: portalMode.value === 'admin' ? 'pi pi-user' : 'pi pi-building',
            class: 'font-semibold text-indigo-600 bg-indigo-50/50',
            command: () => {
                setPortalMode(portalMode.value === 'admin' ? 'employee' : 'admin');
            },
        });
    }

    items.push(
        { separator: true },
        {
            label: 'Keluar (Logout)',
            icon: 'pi pi-sign-out',
            class: '!text-rose-600 font-semibold',
            command: () => {
                router.post('/logout', {}, {
                    onError: () => {
                        window.location.href = '/';
                    },
                });
            },
        }
    );

    return items;
});

// Notifications Popover State
const showNotifications = ref(false);
const notificationWrapper = ref(null);
const notifications = ref([
    {
        id: 1,
        title: 'Pengajuan Lembur Tim Fette',
        user: 'Ahmad Fauzi (Div. Produksi)',
        time: '5 menit yang lalu',
        type: 'lembur',
        badge: 'SPL Menunggu',
        badgeClass: 'bg-amber-100 text-amber-700',
        icon: 'pi pi-clock text-amber-600 bg-amber-50',
    },
    {
        id: 2,
        title: 'Permohonan Cuti Tahunan',
        user: 'Siti Sarah Nabila (Div. SDM)',
        time: '35 menit yang lalu',
        type: 'cuti',
        badge: 'Cuti 3 Hari',
        badgeClass: 'bg-indigo-100 text-indigo-700',
        icon: 'pi pi-calendar-plus text-indigo-600 bg-indigo-50',
    },
    {
        id: 3,
        title: 'Izin Keluar Kantor (Dinas Luar)',
        user: 'dr. Farah Anggraini (Poliklinik)',
        time: '2 jam yang lalu',
        type: 'izin',
        badge: 'Dinas Luar',
        badgeClass: 'bg-orange-100 text-[#F37021]',
        icon: 'pi pi-external-link text-[#F37021] bg-orange-50',
    },
]);

// Quick Search Modal State
const searchModalOpen = ref(false);
const searchQuery = ref('');

const searchMenus = computed(() => {
    const list = [
        { label: 'Dashboard Presensi & Eksekutif', url: '/', category: 'Utama', icon: 'pi pi-home' },
        { label: 'Daftar Pegawai', url: '/datatable', category: 'Kepegawaian', icon: 'pi pi-users' },
        { label: 'Rekapitulasi', url: '/datatable', category: 'Kepegawaian', icon: 'pi pi-table' },
        { label: 'Statistik', url: '/charts', category: 'Kepegawaian', icon: 'pi pi-chart-bar' },
        { label: 'Struktur Organisasi & Jabatan', url: '/components', category: 'Kepegawaian', icon: 'pi pi-sitemap' },
        { label: 'Laporan Absensi Karyawan', url: '/absensi/laporan', category: 'Operasional', icon: 'pi pi-calendar' },
        { label: 'Rekapitulasi Presensi & Kehadiran', url: '/charts', category: 'Operasional', icon: 'pi pi-calendar-check' },
        { label: 'Verifikasi & Approval Lembur (SPL)', url: '/forms', category: 'Operasional', icon: 'pi pi-clock' },
        { label: 'Permohonan & Persetujuan Cuti', url: '/forms', category: 'Operasional', icon: 'pi pi-calendar-minus' },
        { label: 'Slip Gaji & Komponen Payroll', url: '/datatable', category: 'Penggajian', icon: 'pi pi-receipt' },
        { label: 'Konfigurasi Shift Kerja KFPB', url: '/components', category: 'Pengaturan', icon: 'pi pi-sliders-h' },
    ];
    if (!searchQuery.value) return list;
    return list.filter((item) =>
        item.label.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        item.category.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
});

const handleKeydown = (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        searchModalOpen.value = !searchModalOpen.value;
    }
    if (e.key === 'Escape') {
        searchModalOpen.value = false;
        showNotifications.value = false;
    }
};

const handleClickOutside = (e) => {
    if (showNotifications.value && notificationWrapper.value && !notificationWrapper.value.contains(e.target)) {
        showNotifications.value = false;
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
    document.removeEventListener('click', handleClickOutside);
});

// Helper URL match
const isCurrentUrl = (path) => {
    if (!path) return false;
    const current = page.url.split('?')[0];
    if (path === '/' && current === '/') return true;
    return path !== '/' && current.startsWith(path);
};
</script>

<template>
    <div class="min-h-screen bg-[#f5f5f9] text-slate-700 font-sans antialiased flex">

        <!-- Mobile drawer backdrop -->
        <div
            v-if="mobileSidebarOpen"
            class="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-xs lg:hidden transition-opacity"
            @click="mobileSidebarOpen = false"
        />

        <!-- ================= SIDEBAR ================= -->
        <aside
            :class="[
                'fixed top-0 bottom-0 left-0 z-50 w-68 bg-white border-r border-slate-200/80 shadow-[0_2px_12px_rgba(67,89,113,0.06)] flex flex-col transition-transform duration-300 ease-in-out lg:translate-x-0',
                mobileSidebarOpen ? 'translate-x-0' : '-translate-x-full',
            ]"
        >
            <!-- Brand / Logo -->
            <div class="h-16 px-5 border-b border-slate-100 flex items-center justify-between shrink-0">
                <Link href="/" class="flex items-center gap-3 group">
                    <div
                        class="size-9 rounded-xl bg-white border border-slate-200/90 shadow-xs flex items-center justify-center p-1 group-hover:scale-105 transition-transform"
                    >
                        <img src="/images/esdm-icon.png" alt="eSDM Logo" class="size-6 object-contain" />
                    </div>
                    <div class="flex flex-col">
                        <span class="font-bold text-slate-800 text-[15px] leading-tight tracking-tight flex items-center gap-1.5">
                            E-SDM KFPB
                            <span class="text-[9px] font-bold px-1.5 py-0.2 rounded bg-orange-50 text-[#F37021] border border-orange-200/80">v2</span>
                        </span>
                        <span class="text-[10px] font-medium text-slate-400 tracking-tight">
                            Kimia Farma Plant Banjaran
                        </span>
                    </div>
                </Link>

                <!-- Mobile close button -->
                <button
                    type="button"
                    class="lg:hidden p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100"
                    @click="mobileSidebarOpen = false"
                >
                    <i class="pi pi-times text-sm" />
                </button>
            </div>

            <!-- Portal Mode Switcher / Badge Pill -->
            <div class="px-4 pt-3 pb-1">
                <div v-if="hasAdminAccess" class="bg-slate-100/90 p-1 rounded-xl flex items-center text-xs font-medium border border-slate-200/60">
                    <button
                        type="button"
                        :class="[
                            'flex-1 py-1.5 px-2 rounded-lg flex items-center justify-center gap-1.5 transition-all text-[11px] font-semibold cursor-pointer',
                            portalMode === 'admin'
                                ? 'bg-white text-[#005BAC] shadow-xs border border-blue-100'
                                : 'text-slate-500 hover:text-slate-800',
                        ]"
                        @click="setPortalMode('admin')"
                    >
                        <i class="pi pi-building text-xs" />
                        <span>Admin SDM</span>
                    </button>
                    <button
                        type="button"
                        :class="[
                            'flex-1 py-1.5 px-2 rounded-lg flex items-center justify-center gap-1.5 transition-all text-[11px] font-semibold cursor-pointer',
                            portalMode === 'employee'
                                ? 'bg-white text-[#F37021] shadow-xs border border-orange-200'
                                : 'text-slate-500 hover:text-slate-800',
                        ]"
                        @click="setPortalMode('employee')"
                    >
                        <i class="pi pi-user text-xs" />
                        <span>Pegawai Mandiri</span>
                    </button>
                </div>

                <!-- Regular Employee Portal Indicator -->
                <div
                    v-else
                    class="px-3 py-1.5 rounded-lg bg-orange-50/80 border border-orange-200/60 text-[#F37021] text-[11px] font-semibold flex items-center gap-2"
                >
                    <span class="size-2 rounded-full bg-[#F37021] animate-pulse" />
                    <span>Portal Layanan Mandiri (ESS)</span>
                </div>
            </div>

            <!-- ================= NAVIGATION MENU ================= -->
            <nav class="flex-1 overflow-y-auto px-3.5 py-3 space-y-5 custom-scrollbar text-sm">

                <!-- ################################################# -->
                <!-- VIEW A: ADMIN & MANAJEMEN SDM (HR PORTAL)         -->
                <!-- ################################################# -->
                <template v-if="portalMode === 'admin'">
                    <!-- Section: RINGKASAN UTAMA -->
                    <div>
                        <div class="px-3 mb-1.5 text-[10px] font-bold tracking-wider text-slate-400 uppercase">
                            Utama
                        </div>
                        <ul class="space-y-1">
                            <li>
                                <Link
                                    href="/"
                                    :class="[
                                        'flex items-center gap-3 px-3.5 py-2.5 rounded-lg transition-all duration-150 font-medium cursor-pointer',
                                        isCurrentUrl('/')
                                            ? 'bg-[#005BAC] text-white shadow-[0_2px_8px_rgba(0,91,172,0.35)]'
                                            : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900',
                                    ]"
                                >
                                    <i :class="['pi pi-chart-pie text-base shrink-0', isCurrentUrl('/') ? 'text-white' : 'text-slate-400']" />
                                    <span>Dashboard Eksekutif</span>
                                </Link>
                            </li>
                        </ul>
                    </div>

                    <!-- Section: MASTER DATA -->
                    <div>
                        <div class="px-3 mb-1.5 text-[10px] font-bold tracking-wider text-slate-400 uppercase">
                            Master Data
                        </div>
                        <div class="space-y-1">
                            <button
                                type="button"
                                class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors duration-150 font-medium cursor-pointer"
                                @click="toggleGroup('masterData')"
                            >
                                <div class="flex items-center gap-3">
                                    <i class="pi pi-database text-base text-slate-400" />
                                    <span>Master Data</span>
                                </div>
                                <i
                                    :class="[
                                        'pi pi-chevron-right text-xs text-slate-400 transition-transform duration-200',
                                        openGroups['masterData'] ? 'rotate-90' : '',
                                    ]"
                                />
                            </button>

                            <ul v-show="openGroups['masterData']" class="pl-8 pr-1 space-y-1 pt-0.5">
                                <li>
                                    <Link
                                        href="/components"
                                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Data Jabatan</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/admin/master_data/division"
                                        :class="[
                                            'flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition-colors',
                                            isCurrentUrl('/admin/master_data/division')
                                                ? 'bg-[#005BAC]/10 text-[#005BAC] font-semibold'
                                                : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900',
                                        ]"
                                    >
                                        <div class="flex items-center gap-2.5">
                                            <span class="size-1.5 rounded-full bg-current shrink-0" />
                                            <span>Data Bagian</span>
                                        </div>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/admin/master_data/sub_division"
                                        :class="[
                                            'flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition-colors',
                                            isCurrentUrl('/admin/master_data/sub_division')
                                                ? 'bg-[#005BAC]/10 text-[#005BAC] font-semibold'
                                                : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900',
                                        ]"
                                    >
                                        <div class="flex items-center gap-2.5">
                                            <span class="size-1.5 rounded-full bg-current shrink-0" />
                                            <span>Data Sub Bagian</span>
                                        </div>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/admin/master_data/religion"
                                        :class="[
                                            'flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition-colors',
                                            isCurrentUrl('/admin/master_data/religion')
                                                ? 'bg-[#005BAC]/10 text-[#005BAC] font-semibold'
                                                : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900',
                                        ]"
                                    >
                                        <div class="flex items-center gap-2.5">
                                            <span class="size-1.5 rounded-full bg-current shrink-0" />
                                            <span>Data Agama</span>
                                        </div>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/components"
                                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Data Struktur</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/components"
                                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Data Shift</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/components"
                                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Data Libur</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/components"
                                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Data Cuti</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/components"
                                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Status Pegawai</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/components"
                                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Data Bank</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/components"
                                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Data Grading</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/components"
                                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Jabatan Struktural</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/components"
                                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Jabatan Fungsional</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/components"
                                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Data Eselon</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/components"
                                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Data Berkas</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/components"
                                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Data Payroll</span>
                                    </Link>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Section: KEPEGAWAIAN (HR CORE) -->
                    <div>
                        <div class="px-3 mb-1.5 text-[10px] font-bold tracking-wider text-slate-400 uppercase">
                            Manajemen Pegawai
                        </div>
                        <div class="space-y-1">
                            <button
                                type="button"
                                class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors duration-150 font-medium cursor-pointer"
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

                            <ul v-show="openGroups.kepegawaian" class="pl-8 pr-1 space-y-1 pt-0.5">
                                <li>
                                    <Link
                                        href="/datatable"
                                        :class="[
                                            'flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors',
                                            isCurrentUrl('/datatable')
                                                ? 'bg-[#005BAC]/10 text-[#005BAC] font-semibold'
                                                : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900',
                                        ]"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Daftar Pegawai</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/datatable"
                                        :class="[
                                            'flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors',
                                            isCurrentUrl('/datatable')
                                                ? 'bg-[#005BAC]/10 text-[#005BAC] font-semibold'
                                                : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900',
                                        ]"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Daftar Gaji</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/datatable"
                                        :class="[
                                            'flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors',
                                            isCurrentUrl('/datatable')
                                                ? 'bg-[#005BAC]/10 text-[#005BAC] font-semibold'
                                                : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900',
                                        ]"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Rekapitulasi</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/datatable"
                                        :class="[
                                            'flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors',
                                            isCurrentUrl('/datatable')
                                                ? 'bg-[#005BAC]/10 text-[#005BAC] font-semibold'
                                                : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900',
                                        ]"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Statistik</span>
                                    </Link>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Section: OPERASIONAL -->
                    <div>
                        <div class="px-3 mb-1.5 text-[10px] font-bold tracking-wider text-slate-400 uppercase">
                            Operasional
                        </div>
                        <div class="space-y-1">
                            <button
                                type="button"
                                class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors duration-150 font-medium cursor-pointer"
                                @click="toggleGroup('absensi')"
                            >
                                <div class="flex items-center gap-3">
                                    <i class="pi pi-calendar-clock text-base text-slate-400" />
                                    <span>Absensi</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="bg-rose-100 text-rose-700 text-[10px] font-bold px-1.5 py-0.5 rounded-full">
                                        17
                                    </span>
                                    <i
                                        :class="[
                                            'pi pi-chevron-right text-xs text-slate-400 transition-transform duration-200',
                                            openGroups.absensi ? 'rotate-90' : '',
                                        ]"
                                    />
                                </div>
                            </button>

                            <ul v-show="openGroups.absensi" class="pl-8 pr-1 space-y-1 pt-0.5">
                                <li>
                                    <Link
                                        href="/datatable"
                                        :class="[
                                            'flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors',
                                            isCurrentUrl('/datatable')
                                                ? 'bg-[#005BAC]/10 text-[#005BAC] font-semibold'
                                                : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900',
                                        ]"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Data Absensi</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/datatable"
                                        :class="[
                                            'flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition-colors',
                                            isCurrentUrl('/datatable')
                                                ? 'bg-[#005BAC]/10 text-[#005BAC] font-semibold'
                                                : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900',
                                        ]"
                                    >
                                        <div class="flex items-center gap-2.5">
                                            <span class="size-1.5 rounded-full bg-current shrink-0" />
                                            <span>GPS Monitor</span>
                                        </div>
                                        <span class="text-[10px] font-bold bg-amber-100 text-amber-700 px-1.5 py-0.2 rounded-full">12</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/forms"
                                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors text-slate-600 hover:bg-slate-100/80 hover:text-slate-900"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Tarik Absen Wajah</span>
                                    </Link>
                                </li>
                            </ul>
                        </div>

                        <div class="space-y-1">
                            <button
                                type="button"
                                class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors duration-150 font-medium cursor-pointer"
                                @click="toggleGroup('izin')"
                            >
                                <div class="flex items-center gap-3">
                                    <i class="pi pi-user-minus text-base text-slate-400" />
                                    <span>Izin</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="bg-rose-100 text-rose-700 text-[10px] font-bold px-1.5 py-0.5 rounded-full">
                                        17
                                    </span>
                                    <i
                                        :class="[
                                            'pi pi-chevron-right text-xs text-slate-400 transition-transform duration-200',
                                            openGroups.izin ? 'rotate-90' : '',
                                        ]"
                                    />
                                </div>
                            </button>

                            <ul v-show="openGroups.izin" class="pl-8 pr-1 space-y-1 pt-0.5">
                                <li>
                                    <Link
                                        href="/admin/izin"
                                        :class="[
                                            'flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition-colors',
                                            isCurrentUrl('/admin/izin')
                                                ? 'bg-[#005BAC]/10 text-[#005BAC] font-semibold'
                                                : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900',
                                        ]"
                                    >
                                        <div class="flex items-center gap-2.5">
                                            <span class="size-1.5 rounded-full bg-current shrink-0" />
                                            <span>Data Izin & Cuti Pegawai</span>
                                        </div>
                                    </Link>
                                </li>
                            </ul>
                        </div>
                        
                        <div class="space-y-1">
                            <button
                                type="button"
                                class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors duration-150 font-medium cursor-pointer"
                                @click="toggleGroup('lembur')"
                            >
                                <div class="flex items-center gap-3">
                                    <i class="pi pi-clock text-base text-slate-400" />
                                    <span>Lembur</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="bg-rose-100 text-rose-700 text-[10px] font-bold px-1.5 py-0.5 rounded-full">
                                        17
                                    </span>
                                    <i
                                        :class="[
                                            'pi pi-chevron-right text-xs text-slate-400 transition-transform duration-200',
                                            openGroups.lembur ? 'rotate-90' : '',
                                        ]"
                                    />
                                </div>
                            </button>

                            <ul v-show="openGroups.lembur" class="pl-8 pr-1 space-y-1 pt-0.5">
                                <li>
                                    <Link
                                        href="/datatable"
                                        :class="[
                                            'flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors',
                                            isCurrentUrl('/datatable')
                                                ? 'bg-[#005BAC]/10 text-[#005BAC] font-semibold'
                                                : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900',
                                        ]"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Data Lembur</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/datatable"
                                        :class="[
                                            'flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors',
                                            isCurrentUrl('/datatable')
                                                ? 'bg-[#005BAC]/10 text-[#005BAC] font-semibold'
                                                : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900',
                                        ]"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Detail Data Lembur</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/datatable"
                                        :class="[
                                            'flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors',
                                            isCurrentUrl('/datatable')
                                                ? 'bg-[#005BAC]/10 text-[#005BAC] font-semibold'
                                                : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900',
                                        ]"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Data Pengajuan Lembur</span>
                                    </Link>
                                </li>
                            </ul>
                        </div>
                        
                        <div class="space-y-1">
                            <button
                                type="button"
                                class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors duration-150 font-medium cursor-pointer"
                                @click="toggleGroup('rekapitulasi')"
                            >
                                <div class="flex items-center gap-3">
                                    <i class="pi pi-chart-bar text-base text-slate-400" />
                                    <span>Rekapitulasi</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="bg-rose-100 text-rose-700 text-[10px] font-bold px-1.5 py-0.5 rounded-full">
                                        17
                                    </span>
                                    <i
                                        :class="[
                                            'pi pi-chevron-right text-xs text-slate-400 transition-transform duration-200',
                                            openGroups.rekapitulasi ? 'rotate-90' : '',
                                        ]"
                                    />
                                </div>
                            </button>

                            <ul v-show="openGroups.rekapitulasi" class="pl-8 pr-1 space-y-1 pt-0.5">
                                <li>
                                    <Link
                                        href="/admin/absensi/laporan"
                                        :class="[
                                            'flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors',
                                            isCurrentUrl('/admin/absensi/laporan') || isCurrentUrl('/absensi/laporan')
                                                ? 'bg-[#005BAC]/10 text-[#005BAC] font-semibold'
                                                : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900',
                                        ]"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Laporan Absensi</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/admin/izin-keluar"
                                        :class="[
                                            'flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors',
                                            isCurrentUrl('/admin/izin-keluar')
                                                ? 'bg-[#005BAC]/10 text-[#005BAC] font-semibold'
                                                : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900',
                                        ]"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Rekap Izin Keluar Kantor</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/izin-keluar/scan"
                                        :class="[
                                            'flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors',
                                            isCurrentUrl('/izin-keluar/scan')
                                                ? 'bg-[#005BAC]/10 text-[#005BAC] font-semibold'
                                                : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900',
                                        ]"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Scanner QR Pos Satpam</span>
                                    </Link>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Section: PENGGAJIAN (PAYROLL) -->
                    <div>
                        <div class="px-3 mb-1.5 text-[10px] font-bold tracking-wider text-slate-400 uppercase">
                            Kompensasi & Gaji
                        </div>
                        <div class="space-y-1">
                            <button
                                type="button"
                                class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors duration-150 font-medium cursor-pointer"
                                @click="toggleGroup('payroll')"
                            >
                                <div class="flex items-center gap-3">
                                    <i class="pi pi-wallet text-base text-slate-400" />
                                    <span>Penggajian (Payroll)</span>
                                </div>
                                <i
                                    :class="[
                                        'pi pi-chevron-right text-xs text-slate-400 transition-transform duration-200',
                                        openGroups.payroll ? 'rotate-90' : '',
                                    ]"
                                />
                            </button>

                            <ul v-show="openGroups.payroll" class="pl-8 pr-1 space-y-1 pt-0.5">
                                <li>
                                    <Link
                                        href="/components"
                                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Gaji Pokok</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/charts"
                                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Lembur</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/forms"
                                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Potongan</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/datatable"
                                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Pajak</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/datatable"
                                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Proses Payroll</span>
                                    </Link>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Section: PENGATURAN SISTEM -->
                    <div>
                        <div class="px-3 mb-1.5 text-[10px] font-bold tracking-wider text-slate-400 uppercase">
                            Sistem & Konfigurasi
                        </div>
                        <div class="space-y-1">
                            <button
                                type="button"
                                class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors duration-150 font-medium cursor-pointer"
                                @click="toggleGroup('sistem')"
                            >
                                <div class="flex items-center gap-3">
                                    <i class="pi pi-cog text-base text-slate-400" />
                                    <span>Pengaturan Sistem</span>
                                </div>
                                <i
                                    :class="[
                                        'pi pi-chevron-right text-xs text-slate-400 transition-transform duration-200',
                                        openGroups.sistem ? 'rotate-90' : '',
                                    ]"
                                />
                            </button>

                            <ul v-show="openGroups.sistem" class="pl-8 pr-1 space-y-1 pt-0.5">
                                <li>
                                    <Link
                                        href="/components"
                                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Konfigurasi Shift Kerja</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/components"
                                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Kalender & Hari Libur</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/components"
                                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Akun & Hak Akses User</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/components"
                                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Log Audit & Trusted Devices</span>
                                    </Link>
                                </li>
                            </ul>
                        </div>
                    </div>
                </template>

                <!-- ################################################# -->
                <!-- VIEW B: LAYANAN MANDIRI PEGAWAI (EMPLOYEE PORTAL) -->
                <!-- ################################################# -->
                <template v-else>
                    <!-- Section: BERANDA PEGAWAI -->
                    <div>
                        <div class="px-3 mb-1.5 text-[10px] font-bold tracking-wider text-slate-400 uppercase">
                            Beranda Pegawai
                        </div>
                        <ul class="space-y-1">
                            <li>
                                <Link
                                    href="/absensi"
                                    :class="[
                                        'flex items-center gap-3 px-3.5 py-2.5 rounded-lg transition-all duration-150 font-medium cursor-pointer',
                                        isCurrentUrl('/absensi')
                                            ? 'bg-[#005BAC] text-white shadow-[0_2px_8px_rgba(0,91,172,0.35)]'
                                            : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900',
                                    ]"
                                >
                                    <i :class="['pi pi-calendar-check text-base shrink-0', isCurrentUrl('/absensi') ? 'text-white' : 'text-slate-400']" />
                                    <span>Presensi Harian (Clock-In)</span>
                                </Link>
                            </li>
                        </ul>
                    </div>

                    <!-- Section: LAYANAN MANDIRI (ESS) -->
                    <div>
                        <div class="px-3 mb-1.5 text-[10px] font-bold tracking-wider text-slate-400 uppercase">
                            Layanan Mandiri (ESS)
                        </div>
                        <div class="space-y-1">
                            <button
                                type="button"
                                class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors duration-150 font-medium cursor-pointer"
                                @click="toggleGroup('layananMandiri')"
                            >
                                <div class="flex items-center gap-3">
                                    <i class="pi pi-calendar-plus text-base text-slate-400" />
                                    <span>Presensi & Izin Saya</span>
                                </div>
                                <i
                                    :class="[
                                        'pi pi-chevron-right text-xs text-slate-400 transition-transform duration-200',
                                        openGroups.layananMandiri ? 'rotate-90' : '',
                                    ]"
                                />
                            </button>

                            <ul v-show="openGroups.layananMandiri" class="pl-8 pr-1 space-y-1 pt-0.5">
                                <li>
                                    <Link
                                        href="/absensi/laporan"
                                        :class="[
                                            'flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors',
                                            isCurrentUrl('/absensi/laporan')
                                                ? 'bg-[#005BAC]/10 text-[#005BAC] font-semibold'
                                                : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900',
                                        ]"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Laporan & Riwayat Absensi</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/izin/buat"
                                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Lapor Izin / Sakit / Cuti</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/forms"
                                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Pengajuan Lembur (SPL Saya)</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/izin-keluar"
                                        :class="[
                                            'flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors',
                                            isCurrentUrl('/izin-keluar')
                                                ? 'bg-[#005BAC]/10 text-[#005BAC] font-semibold'
                                                : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900',
                                        ]"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Izin Keluar Kantor</span>
                                    </Link>
                                </li>
                                <li v-if="isSecurityGuard">
                                    <Link
                                        href="/izin-keluar/scan"
                                        :class="[
                                            'flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors',
                                            isCurrentUrl('/izin-keluar/scan')
                                                ? 'bg-[#005BAC]/10 text-[#005BAC] font-semibold'
                                                : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900',
                                        ]"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <div class="flex items-center justify-between flex-1">
                                            <span>Scanner QR Satpam</span>
                                            <span class="text-[9px] font-bold px-1.5 py-0.2 rounded bg-emerald-100 text-emerald-800">POS</span>
                                        </div>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/datatable"
                                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Slip Gaji Saya (Privat)</span>
                                    </Link>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Section: PORTOFOLIO & DOKUMEN PRIBADI -->
                    <div>
                        <div class="px-3 mb-1.5 text-[10px] font-bold tracking-wider text-slate-400 uppercase">
                            Data Pribadi
                        </div>
                        <div class="space-y-1">
                            <button
                                type="button"
                                class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors duration-150 font-medium cursor-pointer"
                                @click="toggleGroup('portofolioSaya')"
                            >
                                <div class="flex items-center gap-3">
                                    <i class="pi pi-id-card text-base text-slate-400" />
                                    <span>Biodata & Portofolio</span>
                                </div>
                                <i
                                    :class="[
                                        'pi pi-chevron-right text-xs text-slate-400 transition-transform duration-200',
                                        openGroups.portofolioSaya ? 'rotate-90' : '',
                                    ]"
                                />
                            </button>

                            <ul v-show="openGroups.portofolioSaya" class="pl-8 pr-1 space-y-1 pt-0.5">
                                <li>
                                    <Link
                                        href="/forms"
                                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Biodata & Kontak</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/components"
                                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Data Keluarga & Tanggungan</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/components"
                                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Pendidikan & Pelatihan</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/components"
                                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 transition-colors"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Dokumen Karyawan (KTP/NPWP)</span>
                                    </Link>
                                </li>
                            </ul>
                        </div>
                    </div>
                </template>
            </nav>

            <!-- Sidebar User Quick Footer -->
            <div class="p-3.5 border-t border-slate-100 bg-slate-50/70">
                <div class="flex items-center gap-3 p-2 rounded-xl bg-white border border-slate-200/60 shadow-xs">
                    <Avatar
                        :label="currentUser.avatarInitials"
                        shape="circle"
                        :class="[
                            '!font-bold text-xs shrink-0 size-9',
                            portalMode === 'admin'
                                ? '!bg-[#005BAC]/15 !text-[#005BAC]'
                                : '!bg-orange-500/15 !text-[#F37021]',
                        ]"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold text-slate-800 truncate">
                            {{ currentUser.displayName }}
                        </p>
                        <p class="text-[11px] text-slate-400 truncate">
                            NIP: {{ currentUser.employee_nip || currentUser.username }}
                        </p>
                    </div>
                    <span
                        class="size-2 rounded-full bg-[#005BAC] shrink-0 ring-2 ring-blue-100"
                        title="Status: Online Aktif"
                    />
                </div>
            </div>

            <!-- Sidebar Drawer Copyright -->
            <div class="px-4 py-2.5 border-t border-slate-100 text-[10px] text-slate-400 text-center select-none bg-slate-50/50 shrink-0">
                <span>© 2026 E-SDM KFPB • Plant Banjaran</span>
            </div>
        </aside>

        <!-- ================= MAIN WRAPPER ================= -->
        <div class="flex-1 flex flex-col min-w-0 lg:pl-68 transition-all">

            <!-- ================= FLOATING TOP NAVBAR ================= -->
            <header class="sticky top-3 z-30 mx-4 sm:mx-6 mt-3 mb-2">
                <div
                    class="bg-white/85 backdrop-blur-md rounded-xl border border-slate-200/80 shadow-[0_2px_6px_0_rgba(67,89,113,0.06)] px-4 py-2.5 flex items-center justify-between gap-3"
                >
                    <!-- Left: Mobile Toggle & Page Title / Breadcrumbs -->
                    <div class="flex items-center gap-3 min-w-0">
                        <button
                            type="button"
                            class="lg:hidden p-2 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700 cursor-pointer shrink-0"
                            @click="toggleMobileSidebar"
                        >
                            <i class="pi pi-bars text-lg" />
                        </button>

                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5 text-xs text-slate-400">
                                <Link href="/" class="hover:text-slate-600 transition-colors flex items-center gap-1 shrink-0 whitespace-nowrap">
                                    <i class="pi pi-home text-[11px] shrink-0" />
                                    <span>E-SDM</span>
                                </Link>
                                <i class="pi pi-angle-right text-[10px] shrink-0" />
                                <span class="text-slate-600 font-medium truncate">{{ title }}</span>
                            </div>
                            <h1 class="text-sm font-bold text-slate-800 tracking-tight truncate hidden sm:block">
                                {{ title }}
                            </h1>
                        </div>
                    </div>

                    <!-- Right: Search, Portal Switcher, Notifications & Profile -->
                    <div class="flex items-center gap-2 sm:gap-3 shrink-0">

                        <!-- Mode Switcher Quick Pill (Visible for Admins on Desktop) -->
                        <div v-if="hasAdminAccess" class="hidden md:flex items-center">
                            <button
                                type="button"
                                :class="[
                                    'px-2.5 py-1 rounded-lg text-xs font-semibold flex items-center gap-1.5 border transition-all cursor-pointer',
                                    portalMode === 'admin'
                                        ? 'bg-blue-50 text-[#005BAC] border-blue-200 hover:bg-blue-100'
                                        : 'bg-orange-50 text-[#F37021] border-orange-200 hover:bg-orange-100',
                                ]"
                                @click="setPortalMode(portalMode === 'admin' ? 'employee' : 'admin')"
                                :title="portalMode === 'admin' ? 'Klik untuk berpindah ke tampilan Layanan Pegawai' : 'Klik untuk berpindah ke tampilan Admin HRD'"
                            >
                                <i :class="portalMode === 'admin' ? 'pi pi-building' : 'pi pi-user'" />
                                <span>{{ portalMode === 'admin' ? 'Mode: Admin SDM' : 'Mode: Pegawai Mandiri' }}</span>
                                <i class="pi pi-sort-alt text-[10px] opacity-70" />
                            </button>
                        </div>

                        <!-- Quick Search Button -->
                        <button
                            type="button"
                            class="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-100/80 text-slate-400 hover:text-slate-600 hover:bg-slate-100 text-xs border border-slate-200/60 transition-colors cursor-pointer"
                            @click="searchModalOpen = true"
                        >
                            <i class="pi pi-search text-xs" />
                            <span class="text-slate-500">Cari menu & pegawai...</span>
                            <kbd class="ml-1 font-mono text-[10px] bg-white px-1.5 py-0.5 rounded border border-slate-200 text-slate-400">Ctrl K</kbd>
                        </button>

                        <!-- Notification Button & Dropdown Popover -->
                        <div ref="notificationWrapper" class="relative">
                            <button
                                type="button"
                                class="relative p-2 rounded-xl text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors cursor-pointer"
                                @click="showNotifications = !showNotifications"
                                title="Notifikasi Persetujuan"
                            >
                                <i class="pi pi-bell text-lg" />
                                <span
                                    class="absolute top-1.5 right-1.5 size-2 rounded-full bg-rose-500 ring-2 ring-white animate-pulse"
                                />
                            </button>

                            <!-- Notification Dropdown Popover -->
                            <div
                                v-if="showNotifications"
                                class="absolute -right-14 sm:right-0 mt-2 w-[calc(100vw-3rem)] sm:w-96 max-w-sm sm:max-w-none bg-white rounded-2xl shadow-xl border border-slate-200/80 z-50 p-4 transition-all"
                            >
                                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-xs font-bold text-slate-800">Pemberitahuan</h3>
                                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-rose-100 text-rose-700">3 Baru</span>
                                    </div>
                                    <button
                                        type="button"
                                        class="text-[11px] text-[#005BAC] hover:text-[#004b8e] hover:underline font-medium cursor-pointer"
                                        @click="showNotifications = false"
                                    >
                                        Tandai dibaca
                                    </button>
                                </div>

                                <div class="divide-y divide-slate-100 max-h-[60vh] sm:max-h-80 overflow-y-auto custom-scrollbar my-1">
                                    <div
                                        v-for="item in notifications"
                                        :key="item.id"
                                        class="py-2.5 px-1 hover:bg-slate-50/80 rounded-lg flex items-start gap-3 transition-colors cursor-pointer"
                                    >
                                        <div :class="['p-2 rounded-xl shrink-0 mt-0.5', item.icon]">
                                            <i class="pi text-xs" />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center justify-between gap-1 mb-0.5">
                                                <p class="text-xs font-semibold text-slate-800 truncate">
                                                    {{ item.title }}
                                                </p>
                                                <span :class="['text-[9px] font-bold px-1.5 py-0.5 rounded-full shrink-0', item.badgeClass]">
                                                    {{ item.badge }}
                                                </span>
                                            </div>
                                            <p class="text-[11px] text-slate-500 truncate">
                                                {{ item.user }}
                                            </p>
                                            <span class="text-[10px] text-slate-400 mt-1 block">
                                                {{ item.time }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="pt-2 border-t border-slate-100 text-center">
                                    <Link
                                        href="/forms"
                                        class="text-xs font-semibold text-[#005BAC] hover:text-[#004b8e] inline-flex items-center gap-1"
                                        @click="showNotifications = false"
                                    >
                                        <span>Buka Pusat Verifikasi</span>
                                        <i class="pi pi-arrow-right text-[10px]" />
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <!-- User Profile Dropdown Button -->
                        <div class="flex items-center pl-2 border-l border-slate-200/80">
                            <button
                                type="button"
                                class="flex items-center gap-2.5 p-1 rounded-xl hover:bg-slate-100 transition-colors text-left cursor-pointer"
                                @click="toggleUserMenu"
                                aria-haspopup="true"
                                aria-controls="user_menu"
                            >
                                <Avatar
                                    :label="currentUser.avatarInitials"
                                    shape="circle"
                                    class="!text-white font-bold text-xs size-8 shadow-xs !bg-[#005BAC]"
                                />
                                <div class="hidden sm:block text-left">
                                    <div class="text-xs font-bold text-slate-800 leading-tight">
                                        {{ currentUser.displayName }}
                                    </div>
                                    <div class="text-[10px] font-medium text-slate-400 leading-tight capitalize">
                                        {{ currentUser.positionName }}
                                    </div>
                                </div>
                                <i class="pi pi-chevron-down text-[10px] text-slate-400 hidden sm:block ml-0.5" />
                            </button>

                            <Menu
                                ref="userMenu"
                                id="user_menu"
                                :model="userMenuItems"
                                :popup="true"
                                class="!rounded-xl !shadow-xl !border-slate-200 text-xs"
                            />
                        </div>
                    </div>
                </div>
            </header>

            <!-- ================= MAIN BODY CONTENT ================= -->
            <main class="flex-1 px-4 sm:px-6 py-4">
                <slot />
            </main>

            <!-- ================= MODERN FOOTER ================= -->
            <footer class="px-4 sm:px-6 py-4 border-t border-slate-200/60 text-xs text-slate-400 flex flex-col sm:flex-row items-center justify-between gap-2.5 mt-auto bg-white/40 shrink-0 text-center sm:text-left">
                <div class="flex items-center justify-center sm:justify-start gap-1.5 flex-wrap">
                    <span>© 2026</span>
                    <span class="font-semibold text-slate-700">E-SDM KFPB v2.0</span>
                    <span class="text-slate-300">•</span>
                    <span>PT Kimia Farma Plant Banjaran</span>
                </div>
                <div class="flex items-center justify-center gap-2 text-[11px] text-slate-400 flex-wrap">
                    <span>Database: MariaDB / MySQL</span>
                    <span class="text-slate-300">•</span>
                    <span class="text-slate-500 font-medium">Standard English Schema</span>
                </div>
            </footer>
        </div>

        <!-- ================= QUICK SEARCH MODAL (CTRL+K) ================= -->
        <div
            v-if="searchModalOpen"
            class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-start justify-center pt-20 px-4"
            @click.self="searchModalOpen = false"
        >
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-xl overflow-hidden">
                <div class="p-3.5 border-b border-slate-100 flex items-center gap-3">
                    <i class="pi pi-search text-slate-400 text-base" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Ketik untuk mencari menu, direktori pegawai, atau layanan..."
                        class="w-full text-sm outline-none text-slate-800 placeholder-slate-400"
                        autofocus
                    />
                    <kbd class="text-[10px] font-mono bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded border border-slate-200">ESC</kbd>
                </div>
                <div class="p-2 max-h-80 overflow-y-auto custom-scrollbar">
                    <div v-if="searchMenus.length === 0" class="p-6 text-center text-xs text-slate-400">
                        Tidak ada menu yang sesuai dengan kata kunci "{{ searchQuery }}"
                    </div>
                    <div v-else class="space-y-1">
                        <Link
                            v-for="(item, idx) in searchMenus"
                            :key="idx"
                            :href="item.url"
                            class="flex items-center justify-between p-2.5 rounded-xl hover:bg-slate-50 text-slate-700 hover:text-slate-900 transition-colors"
                            @click="searchModalOpen = false"
                        >
                            <div class="flex items-center gap-3">
                                <div class="size-8 rounded-lg bg-blue-50 text-[#005BAC] flex items-center justify-center">
                                    <i :class="[item.icon, 'text-xs']" />
                                </div>
                                <div>
                                    <p class="text-xs font-semibold">{{ item.label }}</p>
                                    <p class="text-[10px] text-slate-400">{{ item.category }}</p>
                                </div>
                            </div>
                            <i class="pi pi-arrow-right text-[10px] text-slate-300" />
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Custom slim scrollbar */
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
