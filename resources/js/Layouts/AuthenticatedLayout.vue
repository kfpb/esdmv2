<script setup>
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import Avatar from 'primevue/avatar';
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
const displayTitle = computed(() => page.props.pageTitle || props.title);

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
const sidebarRef = ref(null);
const mobileMenuButtonRef = ref(null);
const sidebarCloseButtonRef = ref(null);
const isDesktopViewport = ref(true);

const openMobileSidebar = async () => {
    mobileSidebarOpen.value = true;
    await nextTick();
    sidebarCloseButtonRef.value?.focus();
};

const closeMobileSidebar = async (restoreFocus = true) => {
    if (!mobileSidebarOpen.value) return;
    mobileSidebarOpen.value = false;
    if (restoreFocus && !isDesktopViewport.value) {
        await nextTick();
        mobileMenuButtonRef.value?.focus();
    }
};

const toggleMobileSidebar = () => {
    if (mobileSidebarOpen.value) {
        closeMobileSidebar(true);
    } else {
        openMobileSidebar();
    }
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

const notificationsData = computed(() => page.props.notifications || { count: 0, items: [] });
const notificationCount = computed(() => notificationsData.value.count || 0);
const notifications = computed(() => notificationsData.value.items || []);

// Quick Search Modal State
const searchModalOpen = ref(false);
const searchQuery = ref('');
const searchModalRef = ref(null);
const searchInputRef = ref(null);
const searchTriggerRef = ref(null);
let lastFocusedElement = null;

const getFocusableElements = (container) => {
    if (!container) return [];
    return Array.from(
        container.querySelectorAll(
            'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
        )
    ).filter((el) => el.offsetParent !== null);
};

const trapFocus = (e, container) => {
    if (e.key !== 'Tab' || !container) return;
    const focusables = getFocusableElements(container);
    if (focusables.length === 0) return;

    const first = focusables[0];
    const last = focusables[focusables.length - 1];
    if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
    }
};

const openSearchModal = async () => {
    lastFocusedElement = document.activeElement;
    searchModalOpen.value = true;
    await nextTick();
    searchInputRef.value?.focus();
};

const closeSearchModal = async (restoreFocus = true) => {
    if (!searchModalOpen.value) return;
    searchModalOpen.value = false;
    if (restoreFocus) {
        await nextTick();
        const target = lastFocusedElement && document.contains(lastFocusedElement)
            ? lastFocusedElement
            : searchTriggerRef.value;
        target?.focus?.();
    }
    lastFocusedElement = null;
};

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
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        if (searchModalOpen.value) {
            closeSearchModal(true);
        } else {
            openSearchModal();
        }
        return;
    }
    if (e.key === 'Escape') {
        if (searchModalOpen.value) {
            e.preventDefault();
            closeSearchModal(true);
            return;
        }
        if (showNotifications.value) {
            showNotifications.value = false;
            return;
        }
        if (mobileSidebarOpen.value) {
            closeMobileSidebar(true);
        }
        return;
    }
    if (e.key === 'Tab') {
        if (searchModalOpen.value) {
            trapFocus(e, searchModalRef.value);
        } else if (mobileSidebarOpen.value && !isDesktopViewport.value) {
            trapFocus(e, sidebarRef.value);
        }
    }
};

const handleClickOutside = (e) => {
    if (showNotifications.value && notificationWrapper.value && !notificationWrapper.value.contains(e.target)) {
        showNotifications.value = false;
    }
};

let desktopMediaQuery = null;
let removeNavigateListener = null;

const handleViewportChange = (e) => {
    isDesktopViewport.value = e.matches;
    if (e.matches && mobileSidebarOpen.value) {
        mobileSidebarOpen.value = false;
    } else if (!e.matches && !mobileSidebarOpen.value && sidebarRef.value?.contains(document.activeElement)) {
        document.activeElement.blur();
    }
};

onMounted(() => {
    desktopMediaQuery = window.matchMedia('(min-width: 1024px)');
    isDesktopViewport.value = desktopMediaQuery.matches;
    desktopMediaQuery.addEventListener('change', handleViewportChange);

    removeNavigateListener = router.on('navigate', () => {
        mobileSidebarOpen.value = false;
        searchModalOpen.value = false;
        showNotifications.value = false;
    });

    window.addEventListener('keydown', handleKeydown);
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    desktopMediaQuery?.removeEventListener('change', handleViewportChange);
    removeNavigateListener?.();
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
    <div class="min-h-screen bg-[#f5f5f9] text-slate-700 font-sans antialiased flex overflow-x-clip">
        <a
            href="#main-content"
            class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[60] focus:px-4 focus:py-2.5 focus:rounded-lg focus:bg-[#005BAC] focus:text-white focus:text-sm focus:font-semibold focus:shadow-lg"
        >
            Langsung ke konten utama
        </a>
        <div
            v-if="mobileSidebarOpen"
            class="fixed inset-0 z-40 bg-slate-900/50 lg:hidden transition-opacity"
            @click="closeMobileSidebar(true)"
        />
        <aside
            id="main-sidebar"
            ref="sidebarRef"
            :inert="!isDesktopViewport && !mobileSidebarOpen"
            :class="[
                'fixed top-0 bottom-0 left-0 z-50 w-68 bg-white border-r border-slate-200/80 shadow-[0_2px_12px_rgba(67,89,113,0.06)] flex flex-col transition-transform duration-300 ease-in-out lg:translate-x-0',
                mobileSidebarOpen ? 'translate-x-0' : '-translate-x-full',
            ]"
        >
            <div class="h-16 px-5 border-b border-slate-100 flex items-center justify-between shrink-0">
                <Link href="/" class="flex items-center gap-3 group min-h-11">
                    <div
                        class="size-9 rounded-xl bg-white border border-slate-200/90 shadow-xs flex items-center justify-center p-1 group-hover:scale-105 transition-transform"
                    >
                        <img src="/images/esdm-icon.png" alt="eSDM Logo" class="size-6 object-contain" />
                    </div>
                    <div class="flex flex-col">
                        <span class="font-bold text-slate-800 text-[15px] leading-tight tracking-tight flex items-center gap-1.5">
                            E-SDM KFPB
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-orange-50 text-[#c2410c] border border-orange-200">v2.0</span>
                        </span>
                        <span class="text-[11px] font-medium text-slate-600 tracking-tight">
                            Kimia Farma Plant Banjaran
                        </span>
                    </div>
                </Link>
                <button
                    ref="sidebarCloseButtonRef"
                    type="button"
                    aria-label="Tutup menu navigasi"
                    class="lg:hidden min-w-11 min-h-11 inline-flex items-center justify-center rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-100 cursor-pointer"
                    @click="closeMobileSidebar(true)"
                >
                    <i class="pi pi-times text-base" aria-hidden="true" />
                </button>
            </div>
            <div class="px-4 pt-3 pb-1">
                <div v-if="hasAdminAccess" class="bg-slate-100 p-1 rounded-xl flex items-center text-xs font-medium border border-slate-200/80 gap-1">
                    <button
                        type="button"
                        :class="[
                            'flex-1 min-h-11 lg:min-h-9 py-1.5 px-2 rounded-lg flex items-center justify-center gap-1.5 transition-all text-xs font-semibold cursor-pointer',
                            portalMode === 'admin'
                                ? 'bg-white text-[#005BAC] shadow-xs border border-blue-200'
                                : 'text-slate-600 hover:text-slate-900',
                        ]"
                        @click="setPortalMode('admin')"
                    >
                        <i class="pi pi-building text-xs" aria-hidden="true" />
                        <span>Admin SDM</span>
                    </button>
                    <button
                        type="button"
                        :class="[
                            'flex-1 min-h-11 lg:min-h-9 py-1.5 px-2 rounded-lg flex items-center justify-center gap-1.5 transition-all text-xs font-semibold cursor-pointer',
                            portalMode === 'employee'
                                ? 'bg-white text-[#c2410c] shadow-xs border border-orange-200'
                                : 'text-slate-600 hover:text-slate-900',
                        ]"
                        @click="setPortalMode('employee')"
                    >
                        <i class="pi pi-user text-xs" aria-hidden="true" />
                        <span>Pegawai Mandiri</span>
                    </button>
                </div>
                <div
                    v-else
                    class="px-3 py-2 rounded-lg bg-orange-50 border border-orange-200 text-[#c2410c] text-xs font-semibold flex items-center gap-2"
                >
                    <i class="pi pi-user text-xs shrink-0" aria-hidden="true" />
                    <span>Portal Layanan Mandiri (ESS)</span>
                </div>
            </div>
            <nav aria-label="Menu utama" class="flex-1 overflow-y-auto px-3.5 py-3 space-y-5 custom-scrollbar text-sm">
                <template v-if="portalMode === 'admin'">
                    <div>
                        <div class="px-3 mb-1.5 text-[11px] font-bold tracking-wider text-slate-600 uppercase">
                            Utama
                        </div>
                        <ul class="space-y-1">
                            <li>
                                <Link
                                    href="/"
                                    :class="[
                                        'flex items-center gap-3 px-3.5 py-2.5 min-h-11 rounded-lg transition-all duration-150 font-medium cursor-pointer',
                                        isCurrentUrl('/')
                                            ? 'bg-[#005BAC] text-white shadow-[0_2px_8px_rgba(0,91,172,0.35)]'
                                            : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900',
                                    ]"
                                >
                                    <i :class="['pi pi-chart-pie text-base shrink-0', isCurrentUrl('/') ? 'text-white' : 'text-slate-600']" aria-hidden="true" />
                                    <span>Dashboard Eksekutif</span>
                                </Link>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <div class="px-3 mb-1.5 text-[11px] font-bold tracking-wider text-slate-600 uppercase">
                            Master Data
                        </div>
                        <div class="space-y-1">
                            <button
                                type="button"
                                :aria-expanded="String(openGroups.masterData)"
                                class="w-full flex items-center justify-between px-3.5 py-2.5 min-h-11 rounded-lg text-slate-700 hover:bg-slate-100 hover:text-slate-900 transition-colors duration-150 font-medium cursor-pointer"
                                @click="toggleGroup('masterData')"
                            >
                                <div class="flex items-center gap-3">
                                    <i class="pi pi-database text-base text-slate-600" aria-hidden="true" />
                                    <span>Master Data</span>
                                </div>
                                <i
                                    aria-hidden="true"
                                    :class="[
                                        'pi pi-chevron-right text-xs text-slate-600 transition-transform duration-200',
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
                                        href="/admin/master_data/work_shift"
                                        :class="[
                                            'flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition-colors',
                                            isCurrentUrl('/admin/master_data/work_shift')
                                                ? 'bg-[#005BAC]/10 text-[#005BAC] font-semibold'
                                                : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900',
                                        ]"
                                    >
                                        <div class="flex items-center gap-2.5">
                                            <span class="size-1.5 rounded-full bg-current shrink-0" />
                                            <span>Data Shift Kerja</span>
                                        </div>
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
                                        href="/admin/master_data/employee_status"
                                        :class="[
                                            'flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition-colors',
                                            isCurrentUrl('/admin/master_data/employee_status')
                                                ? 'bg-[#005BAC]/10 text-[#005BAC] font-semibold'
                                                : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900',
                                        ]"
                                    >
                                        <div class="flex items-center gap-2.5">
                                            <span class="size-1.5 rounded-full bg-current shrink-0" />
                                            <span>Status Pegawai</span>
                                        </div>
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
                                        href="/admin/master_data/echelon"
                                        :class="[
                                            'flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition-colors',
                                            isCurrentUrl('/admin/master_data/echelon')
                                                ? 'bg-[#005BAC]/10 text-[#005BAC] font-semibold'
                                                : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900',
                                        ]"
                                    >
                                        <div class="flex items-center gap-2.5">
                                            <span class="size-1.5 rounded-full bg-current shrink-0" />
                                            <span>Data Eselon</span>
                                        </div>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/admin/master_data/document"
                                        :class="[
                                            'flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition-colors',
                                            isCurrentUrl('/admin/master_data/document')
                                                ? 'bg-[#005BAC]/10 text-[#005BAC] font-semibold'
                                                : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900',
                                        ]"
                                    >
                                        <div class="flex items-center gap-2.5">
                                            <span class="size-1.5 rounded-full bg-current shrink-0" />
                                            <span>Data Berkas</span>
                                        </div>
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
                    <div>
                        <div class="px-3 mb-1.5 text-[11px] font-bold tracking-wider text-slate-600 uppercase">
                            Manajemen Pegawai
                        </div>
                        <div class="space-y-1">
                            <button
                                type="button"
                                :aria-expanded="String(openGroups.kepegawaian)"
                                class="w-full flex items-center justify-between px-3.5 py-2.5 min-h-11 rounded-lg text-slate-700 hover:bg-slate-100 hover:text-slate-900 transition-colors duration-150 font-medium cursor-pointer"
                                @click="toggleGroup('kepegawaian')"
                            >
                                <div class="flex items-center gap-3">
                                    <i class="pi pi-users text-base text-slate-600" aria-hidden="true" />
                                    <span>Kepegawaian</span>
                                </div>
                                <i
                                    aria-hidden="true"
                                    :class="[
                                        'pi pi-chevron-right text-xs text-slate-600 transition-transform duration-200',
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
                    <div>
                        <div class="px-3 mb-1.5 text-[11px] font-bold tracking-wider text-slate-600 uppercase">
                            Operasional
                        </div>
                        <div class="space-y-1">
                            <button
                                type="button"
                                :aria-expanded="String(openGroups.absensi)"
                                class="w-full flex items-center justify-between px-3.5 py-2.5 min-h-11 rounded-lg text-slate-700 hover:bg-slate-100 hover:text-slate-900 transition-colors duration-150 font-medium cursor-pointer"
                                @click="toggleGroup('absensi')"
                            >
                                <div class="flex items-center gap-3">
                                    <i class="pi pi-calendar-clock text-base text-slate-600" aria-hidden="true" />
                                    <span>Absensi</span>
                                </div>
                                <i
                                    aria-hidden="true"
                                    :class="[
                                        'pi pi-chevron-right text-xs text-slate-600 transition-transform duration-200',
                                        openGroups.absensi ? 'rotate-90' : '',
                                    ]"
                                />
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
                                :aria-expanded="String(openGroups.izin)"
                                class="w-full flex items-center justify-between px-3.5 py-2.5 min-h-11 rounded-lg text-slate-700 hover:bg-slate-100 hover:text-slate-900 transition-colors duration-150 font-medium cursor-pointer"
                                @click="toggleGroup('izin')"
                            >
                                <div class="flex items-center gap-3">
                                    <i class="pi pi-user-minus text-base text-slate-600" aria-hidden="true" />
                                    <span>Izin</span>
                                </div>
                                <i
                                    aria-hidden="true"
                                    :class="[
                                        'pi pi-chevron-right text-xs text-slate-600 transition-transform duration-200',
                                        openGroups.izin ? 'rotate-90' : '',
                                    ]"
                                />
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
                                :aria-expanded="String(openGroups.lembur)"
                                class="w-full flex items-center justify-between px-3.5 py-2.5 min-h-11 rounded-lg text-slate-700 hover:bg-slate-100 hover:text-slate-900 transition-colors duration-150 font-medium cursor-pointer"
                                @click="toggleGroup('lembur')"
                            >
                                <div class="flex items-center gap-3">
                                    <i class="pi pi-clock text-base text-slate-600" aria-hidden="true" />
                                    <span>Lembur</span>
                                </div>
                                <i
                                    aria-hidden="true"
                                    :class="[
                                        'pi pi-chevron-right text-xs text-slate-600 transition-transform duration-200',
                                        openGroups.lembur ? 'rotate-90' : '',
                                    ]"
                                />
                            </button>

                            <ul v-show="openGroups.lembur" class="pl-8 pr-1 space-y-1 pt-0.5">
                                <li>
                                    <Link
                                        href="/lembur/persetujuan"
                                        :aria-current="isCurrentUrl('/lembur/persetujuan') ? 'page' : undefined"
                                        :class="[
                                            'flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors',
                                            isCurrentUrl('/lembur/persetujuan')
                                                ? 'bg-[#005BAC]/10 text-[#005BAC] font-semibold'
                                                : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900',
                                        ]"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Persetujuan Lembur</span>
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
                                :aria-expanded="String(openGroups.rekapitulasi)"
                                class="w-full flex items-center justify-between px-3.5 py-2.5 min-h-11 rounded-lg text-slate-700 hover:bg-slate-100 hover:text-slate-900 transition-colors duration-150 font-medium cursor-pointer"
                                @click="toggleGroup('rekapitulasi')"
                            >
                                <div class="flex items-center gap-3">
                                    <i class="pi pi-chart-bar text-base text-slate-600" aria-hidden="true" />
                                    <span>Rekapitulasi</span>
                                </div>
                                <i
                                    aria-hidden="true"
                                    :class="[
                                        'pi pi-chevron-right text-xs text-slate-600 transition-transform duration-200',
                                        openGroups.rekapitulasi ? 'rotate-90' : '',
                                    ]"
                                />
                            </button>

                            <ul v-show="openGroups.rekapitulasi" class="pl-8 pr-1 space-y-1 pt-0.5">
                                <li>
                                    <Link
                                        href="/admin/absensi/laporan"
                                        :aria-current="isCurrentUrl('/admin/absensi/laporan') || isCurrentUrl('/absensi/laporan') ? 'page' : undefined"
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
                    <div>
                        <div class="px-3 mb-1.5 text-[11px] font-bold tracking-wider text-slate-600 uppercase">
                            Kompensasi & Gaji
                        </div>
                        <div class="space-y-1">
                            <button
                                type="button"
                                :aria-expanded="String(openGroups.payroll)"
                                class="w-full flex items-center justify-between px-3.5 py-2.5 min-h-11 rounded-lg text-slate-700 hover:bg-slate-100 hover:text-slate-900 transition-colors duration-150 font-medium cursor-pointer"
                                @click="toggleGroup('payroll')"
                            >
                                <div class="flex items-center gap-3">
                                    <i class="pi pi-wallet text-base text-slate-600" aria-hidden="true" />
                                    <span>Penggajian (Payroll)</span>
                                </div>
                                <i
                                    aria-hidden="true"
                                    :class="[
                                        'pi pi-chevron-right text-xs text-slate-600 transition-transform duration-200',
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
                    <div>
                        <div class="px-3 mb-1.5 text-[11px] font-bold tracking-wider text-slate-600 uppercase">
                            Sistem & Konfigurasi
                        </div>
                        <div class="space-y-1">
                            <button
                                type="button"
                                :aria-expanded="String(openGroups.sistem)"
                                class="w-full flex items-center justify-between px-3.5 py-2.5 min-h-11 rounded-lg text-slate-700 hover:bg-slate-100 hover:text-slate-900 transition-colors duration-150 font-medium cursor-pointer"
                                @click="toggleGroup('sistem')"
                            >
                                <div class="flex items-center gap-3">
                                    <i class="pi pi-cog text-base text-slate-600" aria-hidden="true" />
                                    <span>Pengaturan Sistem</span>
                                </div>
                                <i
                                    aria-hidden="true"
                                    :class="[
                                        'pi pi-chevron-right text-xs text-slate-600 transition-transform duration-200',
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
                <template v-else>
                    <div>
                        <div class="px-3 mb-1.5 text-[11px] font-bold tracking-wider text-slate-600 uppercase">
                            Beranda Pegawai
                        </div>
                        <ul class="space-y-1">
                            <li>
                                <Link
                                    href="/absensi"
                                    :class="[
                                        'flex items-center gap-3 px-3.5 py-2.5 min-h-11 rounded-lg transition-all duration-150 font-medium cursor-pointer',
                                        isCurrentUrl('/absensi')
                                            ? 'bg-[#005BAC] text-white shadow-[0_2px_8px_rgba(0,91,172,0.35)]'
                                            : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900',
                                    ]"
                                >
                                    <i :class="['pi pi-calendar-check text-base shrink-0', isCurrentUrl('/absensi') ? 'text-white' : 'text-slate-600']" aria-hidden="true" />
                                    <span>Presensi Harian (Clock-In)</span>
                                </Link>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <div class="px-3 mb-1.5 text-[11px] font-bold tracking-wider text-slate-600 uppercase">
                            Layanan Mandiri (ESS)
                        </div>
                        <div class="space-y-1">
                            <button
                                type="button"
                                :aria-expanded="String(openGroups.layananMandiri)"
                                class="w-full flex items-center justify-between px-3.5 py-2.5 min-h-11 rounded-lg text-slate-700 hover:bg-slate-100 hover:text-slate-900 transition-colors duration-150 font-medium cursor-pointer"
                                @click="toggleGroup('layananMandiri')"
                            >
                                <div class="flex items-center gap-3">
                                    <i class="pi pi-calendar-plus text-base text-slate-600" aria-hidden="true" />
                                    <span>Presensi & Izin Saya</span>
                                </div>
                                <i
                                    aria-hidden="true"
                                    :class="[
                                        'pi pi-chevron-right text-xs text-slate-600 transition-transform duration-200',
                                        openGroups.layananMandiri ? 'rotate-90' : '',
                                    ]"
                                />
                            </button>

                            <ul v-show="openGroups.layananMandiri" class="pl-8 pr-1 space-y-1 pt-0.5">
                                <li>
                                    <Link
                                        href="/absensi/laporan"
                                        :aria-current="isCurrentUrl('/absensi/laporan') ? 'page' : undefined"
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
                                        :class="[
                                            'flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors',
                                            isCurrentUrl('/izin/buat')
                                                ? 'bg-[#005BAC]/10 text-[#005BAC] font-semibold'
                                                : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900',
                                        ]"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Lapor Izin / Sakit / Cuti</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/lembur/ajukan"
                                        :aria-current="isCurrentUrl('/lembur/ajukan') ? 'page' : undefined"
                                        :class="[
                                            'flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors',
                                            isCurrentUrl('/lembur/ajukan')
                                                ? 'bg-[#005BAC]/10 text-[#005BAC] font-semibold'
                                                : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900',
                                        ]"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Pengajuan Lembur (SPL Saya)</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/lembur/persetujuan"
                                        :aria-current="isCurrentUrl('/lembur/persetujuan') ? 'page' : undefined"
                                        :class="[
                                            'flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors',
                                            isCurrentUrl('/lembur/persetujuan')
                                                ? 'bg-[#005BAC]/10 text-[#005BAC] font-semibold'
                                                : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900',
                                        ]"
                                    >
                                        <span class="size-1.5 rounded-full bg-current shrink-0" />
                                        <span>Persetujuan Lembur</span>
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
                    <div>
                        <div class="px-3 mb-1.5 text-[11px] font-bold tracking-wider text-slate-600 uppercase">
                            Data Pribadi
                        </div>
                        <div class="space-y-1">
                            <button
                                type="button"
                                :aria-expanded="String(openGroups.portofolioSaya)"
                                class="w-full flex items-center justify-between px-3.5 py-2.5 min-h-11 rounded-lg text-slate-700 hover:bg-slate-100 hover:text-slate-900 transition-colors duration-150 font-medium cursor-pointer"
                                @click="toggleGroup('portofolioSaya')"
                            >
                                <div class="flex items-center gap-3">
                                    <i class="pi pi-id-card text-base text-slate-600" aria-hidden="true" />
                                    <span>Biodata & Portofolio</span>
                                </div>
                                <i
                                    aria-hidden="true"
                                    :class="[
                                        'pi pi-chevron-right text-xs text-slate-600 transition-transform duration-200',
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
            <div class="p-3.5 border-t border-slate-100 bg-slate-50">
                <div class="flex items-center gap-3 p-2.5 rounded-xl bg-white border border-slate-200 shadow-xs">
                    <Avatar
                        :label="currentUser.avatarInitials"
                        shape="circle"
                        :class="[
                            '!font-bold text-xs shrink-0 size-9',
                            portalMode === 'admin'
                                ? '!bg-[#005BAC]/15 !text-[#005BAC]'
                                : '!bg-orange-100 !text-[#9a3412]',
                        ]"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold text-slate-800 truncate">
                            {{ currentUser.displayName }}
                        </p>
                        <p class="text-xs text-slate-600 truncate">
                            NIP: {{ currentUser.employee_nip || currentUser.username }}
                        </p>
                    </div>
                </div>
            </div>
            <div class="px-4 py-2.5 border-t border-slate-100 text-[11px] text-slate-600 text-center bg-slate-50 shrink-0">
                <span>© 2026 E-SDM KFPB • Plant Banjaran</span>
            </div>
        </aside>
        <div class="flex-1 flex flex-col min-w-0 lg:pl-68 transition-all">
            <header class="sticky top-0 z-30 bg-[#f5f5f9] pt-2.5 sm:pt-3 pb-2 px-4 sm:px-6">
                <div
                    class="bg-white rounded-xl border border-slate-200 shadow-[0_2px_6px_0_rgba(67,89,113,0.06)] px-3 sm:px-4 py-2 flex items-center justify-between gap-3"
                >
                    <div class="flex items-center gap-3 min-w-0">
                        <button
                            ref="mobileMenuButtonRef"
                            type="button"
                            aria-label="Buka menu navigasi"
                            :aria-expanded="String(mobileSidebarOpen)"
                            aria-controls="main-sidebar"
                            class="lg:hidden min-w-11 min-h-11 inline-flex items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 hover:text-slate-900 cursor-pointer shrink-0"
                            @click="toggleMobileSidebar"
                        >
                            <i class="pi pi-bars text-lg" aria-hidden="true" />
                        </button>

                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5 text-xs text-slate-600">
                                <Link href="/" class="hover:text-slate-900 transition-colors flex items-center gap-1 shrink-0 whitespace-nowrap">
                                    <i class="pi pi-home text-xs shrink-0" aria-hidden="true" />
                                    <span>E-SDM</span>
                                </Link>
                                <i class="pi pi-angle-right text-xs shrink-0" aria-hidden="true" />
                                <span class="text-slate-700 font-medium truncate">{{ displayTitle }}</span>
                            </div>
                            <p class="text-sm font-bold text-slate-800 tracking-tight truncate hidden sm:block">
                                {{ displayTitle }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                        <div v-if="hasAdminAccess" class="hidden md:flex items-center">
                            <button
                                type="button"
                                :class="[
                                    'min-h-11 px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 border transition-all cursor-pointer',
                                    portalMode === 'admin'
                                        ? 'bg-blue-50 text-[#005BAC] border-blue-200 hover:bg-blue-100'
                                        : 'bg-orange-50 text-[#c2410c] border-orange-200 hover:bg-orange-100',
                                ]"
                                @click="setPortalMode(portalMode === 'admin' ? 'employee' : 'admin')"
                                :title="portalMode === 'admin' ? 'Klik untuk berpindah ke tampilan Layanan Pegawai' : 'Klik untuk berpindah ke tampilan Admin HRD'"
                            >
                                <i :class="portalMode === 'admin' ? 'pi pi-building' : 'pi pi-user'" aria-hidden="true" />
                                <span><span class="hidden lg:inline">Mode: </span>{{ portalMode === 'admin' ? 'Admin SDM' : 'Pegawai Mandiri' }}</span>
                                <i class="pi pi-sort-alt text-xs opacity-80" aria-hidden="true" />
                            </button>
                        </div>
                        <button
                            ref="searchTriggerRef"
                            type="button"
                            class="hidden sm:flex items-center gap-2 min-w-11 min-h-11 px-3 py-1.5 rounded-lg bg-slate-100 text-slate-600 hover:text-slate-900 hover:bg-slate-200/70 text-xs border border-slate-200 transition-colors cursor-pointer shrink-0"
                            @click="openSearchModal"
                        >
                            <i class="pi pi-search text-xs" aria-hidden="true" />
                            <span class="hidden lg:inline text-slate-700 font-medium">Cari menu &amp; pegawai...</span>
                            <span class="lg:hidden text-slate-700 font-medium">Cari</span>
                            <kbd class="hidden lg:inline-block ml-1 font-mono text-[11px] bg-white px-1.5 py-0.5 rounded border border-slate-300 text-slate-600">Ctrl K</kbd>
                        </button>
                        <div ref="notificationWrapper" class="relative">
                            <button
                                type="button"
                                :aria-label="notificationCount > 0 ? `Pusat pemberitahuan, ${notificationCount > 99 ? '99+' : notificationCount} belum dibaca` : 'Pusat pemberitahuan'"
                                :aria-expanded="String(showNotifications)"
                                aria-controls="notifications-popover"
                                class="relative min-w-11 min-h-11 inline-flex items-center justify-center rounded-xl text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors cursor-pointer"
                                @click="showNotifications = !showNotifications"
                            >
                                <i class="pi pi-bell text-lg" aria-hidden="true" />
                                <span
                                    v-if="notificationCount > 0"
                                    aria-hidden="true"
                                    class="absolute top-1 right-1 min-w-4 h-4 px-1 rounded-full bg-rose-700 text-white font-bold text-[10px] flex items-center justify-center ring-2 ring-white shadow-xs"
                                >
                                    {{ notificationCount > 99 ? '99+' : notificationCount }}
                                </span>
                            </button>
                            <div
                                v-if="showNotifications"
                                id="notifications-popover"
                                role="region"
                                aria-label="Daftar pemberitahuan"
                                class="absolute -right-14 sm:right-0 mt-2 w-[calc(100vw-3rem)] sm:w-96 max-w-sm sm:max-w-none bg-white rounded-2xl shadow-xl border border-slate-200 z-50 p-4 transition-all"
                            >
                                <div class="flex items-center justify-between pb-3 border-b border-slate-100 gap-2">
                                    <div class="flex items-center gap-2">
                                        <h2 class="text-xs font-bold text-slate-800">Pemberitahuan</h2>
                                        <span
                                            v-if="notificationCount > 0"
                                            class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-rose-100 text-rose-800"
                                        >
                                            {{ notificationCount }} Baru
                                        </span>
                                        <span
                                            v-else
                                            class="text-[11px] font-medium px-2 py-0.5 rounded-full bg-slate-100 text-slate-600"
                                        >
                                            Tidak ada baru
                                        </span>
                                    </div>
                                    <button
                                        type="button"
                                        class="min-h-11 px-3 inline-flex items-center justify-center rounded-lg text-xs text-slate-600 hover:text-slate-900 hover:bg-slate-100 font-semibold cursor-pointer"
                                        @click="showNotifications = false"
                                    >
                                        Tutup
                                    </button>
                                </div>

                                <div class="divide-y divide-slate-100 max-h-[60vh] sm:max-h-80 overflow-y-auto custom-scrollbar my-1">
                                    <div v-if="notifications.length === 0" class="py-8 text-center text-slate-600">
                                        <i class="pi pi-bell-slash text-2xl text-slate-500 mb-2 block" aria-hidden="true" />
                                        <p class="text-xs font-medium text-slate-700">Tidak ada pemberitahuan baru</p>
                                        <p class="text-xs text-slate-600 mt-0.5">Semua tugas persetujuan dan verifikasi telah selesai.</p>
                                    </div>

                                    <component
                                        :is="item.url && item.url !== '#' ? Link : 'div'"
                                        v-for="item in notifications"
                                        :key="item.id"
                                        :href="item.url && item.url !== '#' ? item.url : undefined"
                                        :class="[
                                            'py-2.5 px-2 rounded-lg flex items-start gap-3 transition-colors',
                                            item.url && item.url !== '#' ? 'hover:bg-slate-50 cursor-pointer' : '',
                                        ]"
                                        @click="item.url && item.url !== '#' ? (showNotifications = false) : undefined"
                                    >
                                        <div :class="['size-8 rounded-xl shrink-0 mt-0.5 flex items-center justify-center', item.icon]">
                                            <i :class="item.icon.split(' ')[0] + ' ' + item.icon.split(' ')[1]" class="text-xs" aria-hidden="true" />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center justify-between gap-1 mb-0.5">
                                                <p class="text-xs font-bold text-slate-800 truncate">
                                                    {{ item.title }}
                                                </p>
                                                <span :class="['text-[10px] font-bold px-1.5 py-0.5 rounded-full shrink-0', item.badgeClass]">
                                                    {{ item.badge }}
                                                </span>
                                            </div>
                                            <p class="text-xs text-slate-600 line-clamp-2 leading-relaxed">
                                                {{ item.message }}
                                            </p>
                                            <span class="text-[11px] text-slate-600 mt-1 block">
                                                {{ item.time }}
                                            </span>
                                        </div>
                                    </component>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center pl-2 border-l border-slate-200">
                            <button
                                type="button"
                                class="min-w-11 min-h-11 px-1.5 flex items-center justify-center gap-2.5 rounded-xl hover:bg-slate-100 transition-colors text-left cursor-pointer"
                                @click="toggleUserMenu"
                                aria-haspopup="true"
                                aria-controls="user_menu"
                            >
                                <Avatar
                                    :label="currentUser.avatarInitials"
                                    shape="circle"
                                    aria-hidden="true"
                                    class="!text-white font-bold text-xs size-8 shadow-xs !bg-[#005BAC]"
                                />
                                <span class="sr-only sm:hidden">Menu akun {{ currentUser.displayName }}</span>
                                <div class="hidden sm:block text-left">
                                    <div class="text-xs font-bold text-slate-800 leading-tight">
                                        {{ currentUser.displayName }}
                                    </div>
                                    <div class="text-[11px] font-medium text-slate-600 leading-tight capitalize">
                                        {{ currentUser.positionName }}
                                    </div>
                                </div>
                                <i class="pi pi-chevron-down text-xs text-slate-600 hidden sm:block ml-0.5" aria-hidden="true" />
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
            <main id="main-content" tabindex="-1" class="flex-1 px-4 sm:px-6 py-4 focus:outline-none">
                <slot />
            </main>
            <footer class="px-4 sm:px-6 py-4 border-t border-slate-200 text-xs text-slate-600 flex flex-col sm:flex-row items-center justify-between gap-2.5 mt-auto bg-white shrink-0 text-center sm:text-left">
                <div class="flex items-center justify-center sm:justify-start gap-1.5 flex-wrap">
                    <span>© 2026</span>
                    <span class="font-semibold text-slate-800">E-SDM KFPB v2.0</span>
                    <span class="text-slate-500" aria-hidden="true">•</span>
                    <span>PT Kimia Farma Plant Banjaran</span>
                </div>
                <div class="flex items-center justify-center gap-2 text-xs text-slate-600 flex-wrap">
                    <span>Portal Sumber Daya Manusia Internal</span>
                </div>
            </footer>
        </div>
        <div
            v-if="searchModalOpen"
            role="dialog"
            aria-modal="true"
            aria-labelledby="quick-search-title"
            class="fixed inset-0 z-50 bg-slate-900/60 flex items-start justify-center pt-20 px-4"
            @click.self="closeSearchModal(true)"
        >
            <div ref="searchModalRef" class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-xl overflow-hidden">
                <h2 id="quick-search-title" class="sr-only">Pencarian Cepat Menu &amp; Pegawai</h2>
                <div class="p-3.5 border-b border-slate-100 flex items-center gap-2.5">
                    <i class="pi pi-search text-slate-600 text-base shrink-0" aria-hidden="true" />
                    <label for="quick-search-input" class="sr-only">Cari menu, direktori pegawai, atau layanan</label>
                    <input
                        id="quick-search-input"
                        ref="searchInputRef"
                        v-model="searchQuery"
                        type="text"
                        placeholder="Ketik untuk mencari menu, direktori pegawai, atau layanan..."
                        class="w-full text-sm text-slate-800 placeholder-slate-500 px-2 py-1.5 rounded-md focus-visible:outline-2 focus-visible:outline-[#005BAC]"
                    />
                    <kbd class="hidden sm:inline text-[11px] font-mono bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded border border-slate-300 shrink-0">ESC</kbd>
                    <button
                        type="button"
                        aria-label="Tutup pencarian"
                        class="min-w-11 min-h-11 inline-flex items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 hover:text-slate-900 cursor-pointer shrink-0"
                        @click="closeSearchModal(true)"
                    >
                        <i class="pi pi-times text-sm" aria-hidden="true" />
                    </button>
                </div>
                <div class="p-2 max-h-80 overflow-y-auto custom-scrollbar">
                    <div v-if="searchMenus.length === 0" class="p-6 text-center text-xs text-slate-600">
                        Tidak ada menu yang sesuai dengan kata kunci "{{ searchQuery }}"
                    </div>
                    <div v-else class="space-y-1">
                        <Link
                            v-for="(item, idx) in searchMenus"
                            :key="idx"
                            :href="item.url"
                            class="flex items-center justify-between p-2.5 rounded-xl hover:bg-slate-50 text-slate-700 hover:text-slate-900 transition-colors"
                            @click="closeSearchModal(false)"
                        >
                            <div class="flex items-center gap-3">
                                <div class="size-8 rounded-lg bg-blue-50 text-[#005BAC] flex items-center justify-center">
                                    <i :class="[item.icon, 'text-xs']" aria-hidden="true" />
                                </div>
                                <div>
                                    <p class="text-xs font-semibold">{{ item.label }}</p>
                                    <p class="text-xs text-slate-600">{{ item.category }}</p>
                                </div>
                            </div>
                            <i class="pi pi-arrow-right text-xs text-slate-500" aria-hidden="true" />
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
