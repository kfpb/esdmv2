<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, reactive, computed, watch, onMounted, onUnmounted } from 'vue';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Select from 'primevue/select';
import DatePicker from 'primevue/datepicker';
import Button from 'primevue/button';
import { exportTableToPdf } from '@/Utils/exportPdf';

defineOptions({ layout: AuthenticatedLayout });

const props = defineProps({
    attendances: {
        type: Array,
        default: () => [],
    },
    targetEmployee: {
        type: Object,
        default: () => ({
            nip: '',
            name: '',
            division: '',
            position: '',
        }),
    },
    employeeOptions: {
        type: Array,
        default: () => [],
    },
    isSupervisor: {
        type: Boolean,
        default: false,
    },
    isAdmin: {
        type: Boolean,
        default: false,
    },
    filters: {
        type: Object,
        default: () => ({
            nip: '',
            start_date: '',
            end_date: '',
            sumber: 'all',
        }),
    },
    statistics: {
        type: Object,
        default: () => ({
            total: 0,
            hadir: 0,
            terlambat: 0,
            izin: 0,
            lembur: 0,
            alpha: 0,
            libur: 0,
        }),
    },
});

const toast = useToast();

function parseDateLocal(str) {
    if (!str || typeof str !== 'string') return null;
    const parts = str.split('-');
    if (parts.length !== 3) return null;
    const year = Number(parts[0]);
    const month = Number(parts[1]);
    const day = Number(parts[2]);
    if (!year || !month || !day) return null;
    return new Date(year, month - 1, day);
}

function formatDateLocal(val) {
    if (!val) return '';
    if (typeof val === 'string') return val;
    if (!(val instanceof Date) || Number.isNaN(val.getTime())) return '';
    const y = val.getFullYear();
    const m = String(val.getMonth() + 1).padStart(2, '0');
    const d = String(val.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

function formatDisplayDate(str) {
    if (!str || typeof str !== 'string') return '-';
    const parts = str.split('-');
    if (parts.length !== 3) return str;
    return `${parts[2]}/${parts[1]}/${parts[0]}`;
}

function getCurrentMonthBounds() {
    const now = new Date();
    const y = now.getFullYear();
    const mIndex = now.getMonth();
    const startObj = new Date(y, mIndex, 1);
    const endObj = new Date(y, mIndex + 1, 0);
    return {
        startObj,
        endObj,
        startStr: formatDateLocal(startObj),
        endStr: formatDateLocal(endObj),
    };
}

const defaultBounds = getCurrentMonthBounds();

const filterForm = reactive({
    nip: props.filters.nip || props.targetEmployee?.nip || '',
    start_date: props.filters.start_date || defaultBounds.startStr,
    end_date: props.filters.end_date || defaultBounds.endStr,
    sumber: props.filters.sumber || 'all',
});

const selectedStartDate = ref(parseDateLocal(filterForm.start_date) || defaultBounds.startObj);
const selectedEndDate = ref(parseDateLocal(filterForm.end_date) || defaultBounds.endObj);

watch(selectedStartDate, (val) => {
    const formatted = formatDateLocal(val);
    if (formatted) {
        filterForm.start_date = formatted;
    }
});

watch(selectedEndDate, (val) => {
    const formatted = formatDateLocal(val);
    if (formatted) {
        filterForm.end_date = formatted;
    }
});

watch(
    () => props.filters,
    (next) => {
        if (!next) return;
        if (next.nip !== undefined) filterForm.nip = next.nip || props.targetEmployee?.nip || '';
        if (next.start_date) {
            filterForm.start_date = next.start_date;
            selectedStartDate.value = parseDateLocal(next.start_date);
        }
        if (next.end_date) {
            filterForm.end_date = next.end_date;
            selectedEndDate.value = parseDateLocal(next.end_date);
        }
        if (next.sumber) filterForm.sumber = next.sumber;
    },
    { deep: true }
);

const isLoadingReport = ref(false);
let removeStartListener = null;
let removeFinishListener = null;

onMounted(() => {
    removeStartListener = router.on('start', (event) => {
        const visitUrl = event?.detail?.visit?.url?.pathname || '';
        if (visitUrl.includes('/absensi/laporan')) {
            isLoadingReport.value = true;
        }
    });
    removeFinishListener = router.on('finish', () => {
        isLoadingReport.value = false;
    });
});

onUnmounted(() => {
    if (typeof removeStartListener === 'function') removeStartListener();
    if (typeof removeFinishListener === 'function') removeFinishListener();
});

// State Pencarian Instan, Paginasi Desktop, dan Kartu Responsif
const instantSearch = ref('');
const statusFilter = ref(null);
const tableFirst = ref(0);
const tableRows = ref(31);
const responsivePage = ref(1);
const responsivePerPage = 16;

const sumberOptions = [
    { value: 'all', label: '-- Semua Sumber --' },
    { value: 'esdm', label: 'eSDM (Presensi Web)' },
    { value: 'wajah', label: 'Absen Wajah (Face-ID)' },
];

const statusFilterOptions = [
    { value: null, label: '-- Semua Status --' },
    { value: 'Hadir Tepat Waktu', label: 'Hadir Tepat Waktu' },
    { value: 'Terlambat', label: 'Terlambat' },
    { value: 'Izin', label: 'Izin / Cuti / Sakit' },
    { value: 'Lembur', label: 'Lembur' },
    { value: 'Libur', label: 'Hari Libur (Mingguan & Nasional)' },
    { value: 'Alpha', label: 'Tidak Hadir (Alpha)' },
];

const filteredAttendances = computed(() => {
    const list = props.attendances || [];
    const q = instantSearch.value.toLowerCase().trim();
    const status = statusFilter.value;

    return list.filter((item) => {
        const matchSearch =
            !q ||
            (item.day_name && item.day_name.toLowerCase().includes(q)) ||
            (item.formatted_date && item.formatted_date.toLowerCase().includes(q)) ||
            (item.date && item.date.toLowerCase().includes(q)) ||
            (item.shift_name && item.shift_name.toLowerCase().includes(q)) ||
            (item.check_in && item.check_in.toLowerCase().includes(q)) ||
            (item.check_out && item.check_out.toLowerCase().includes(q)) ||
            (item.status && item.status.toLowerCase().includes(q)) ||
            (item.notes && item.notes.toLowerCase().includes(q));

        let matchStatus = true;
        if (status === 'Hadir Tepat Waktu') {
            matchStatus = item.status === 'Hadir Tepat Waktu';
        } else if (status === 'Terlambat') {
            matchStatus = item.status === 'Terlambat' || item.is_late;
        } else if (status === 'Izin') {
            matchStatus = item.badge_type === 'izin';
        } else if (status === 'Lembur') {
            matchStatus = (item.overtime_duration && item.overtime_duration !== '-') || item.badge_type === 'lembur_libur';
        } else if (status === 'Libur') {
            matchStatus = ['weekend', 'libur_nasional', 'lembur_libur'].includes(item.badge_type);
        } else if (status === 'Alpha') {
            matchStatus = item.badge_type === 'alpha';
        }

        return matchSearch && matchStatus;
    });
});

watch([instantSearch, statusFilter, () => props.attendances], () => {
    tableFirst.value = 0;
    responsivePage.value = 1;
});

const responsiveTotalPages = computed(() => Math.max(1, Math.ceil(filteredAttendances.value.length / responsivePerPage)));
const paginatedCardAttendances = computed(() => {
    const start = (responsivePage.value - 1) * responsivePerPage;
    return filteredAttendances.value.slice(start, start + responsivePerPage);
});

const activePeriodLabel = computed(() => {
    const startStr = props.filters?.start_date || filterForm.start_date;
    const endStr = props.filters?.end_date || filterForm.end_date;
    return `${formatDisplayDate(startStr)} s/d ${formatDisplayDate(endStr)}`;
});

function getRowClass(data) {
    if (!data) return '';
    if (data.badge_type === 'weekend') return 'row-weekend';
    if (data.badge_type === 'libur_nasional') return 'row-libur-nasional';
    if (data.badge_type === 'lembur_libur') return 'row-lembur-libur';
    if (data.badge_type === 'alpha') return 'row-alpha';
    if (data.badge_type === 'belum_berjalan') return 'row-belum-berjalan';
    return '';
}

function getCardClass(data) {
    if (!data) return 'bg-white border-slate-200';
    if (data.badge_type === 'weekend') return 'bg-[#fee2e2] border-rose-300';
    if (data.badge_type === 'libur_nasional') return 'bg-[#fef3c7] border-amber-300';
    if (data.badge_type === 'lembur_libur') return 'bg-[#dcfce7] border-emerald-300';
    if (data.badge_type === 'alpha') return 'bg-[#e2e8f0] border-slate-300';
    return 'bg-white border-slate-200';
}

function applyFilter() {
    if (filterForm.start_date && filterForm.end_date && filterForm.end_date < filterForm.start_date) {
        toast.add({
            severity: 'warn',
            summary: 'Rentang Tanggal Tidak Valid',
            detail: 'Tanggal akhir tidak boleh lebih awal dari tanggal awal.',
            life: 4000,
        });
        return;
    }

    const targetUrl = typeof window !== 'undefined' ? window.location.pathname : '/absensi/laporan';
    isLoadingReport.value = true;
    router.get(
        targetUrl,
        {
            nip: filterForm.nip,
            start_date: filterForm.start_date,
            end_date: filterForm.end_date,
            sumber: filterForm.sumber,
        },
        {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                isLoadingReport.value = false;
            },
        }
    );
}

function resetFilter() {
    const bounds = getCurrentMonthBounds();
    filterForm.nip = props.targetEmployee?.nip || '';
    filterForm.start_date = bounds.startStr;
    filterForm.end_date = bounds.endStr;
    selectedStartDate.value = bounds.startObj;
    selectedEndDate.value = bounds.endObj;
    filterForm.sumber = 'all';
    instantSearch.value = '';
    statusFilter.value = null;
    applyFilter();
}

function clearTableFilters() {
    instantSearch.value = '';
    statusFilter.value = null;
}

function refreshReport() {
    isLoadingReport.value = true;
    router.reload({
        only: ['attendances', 'statistics', 'targetEmployee', 'filters'],
        onFinish: () => {
            isLoadingReport.value = false;
        },
    });
}

function handleExportPdf() {
    const list = filteredAttendances.value || [];
    if (list.length === 0) {
        toast.add({
            severity: 'warn',
            summary: 'Data Kosong',
            detail: 'Tidak ada data absensi pada filter aktif yang dapat diekspor ke PDF.',
            life: 4000,
        });
        return;
    }

    const dataToExport = [...list].sort((a, b) => a.date.localeCompare(b.date));

    const sumberObj = sumberOptions.find((s) => s.value === filterForm.sumber);
    const sumberLabel = sumberObj ? sumberObj.label : 'Semua Sumber';

    const legendItems = [
        { label: 'Sabtu/Minggu (Libur Mingguan)', color: '#fee2e2', border: '#e27c7c' },
        { label: 'Libur Nasional', color: '#fef3c7', border: '#d9a343' },
        { label: 'Lembur/Masuk di Hari Libur', color: '#dcfce7', border: '#74c476' },
        { label: 'Tidak Hadir (Alpha)', color: '#e2e8f0', border: '#9ca3af' },
        { label: 'Belum Berjalan', color: '#ffffff', border: '#cbd5e1' },
    ];

    exportTableToPdf({
        title: 'LAPORAN REKAPITULASI ABSENSI KARYAWAN',
        subtitle: 'Sistem Elektronik Sumber Daya Manusia (eSDM) - Plant Banjaran',
        documentNo: 'KFPB-SDM-LAPABS-01',
        paperSize: 'A4',
        orientation: 'portrait',
        metaInfo: [
            { label: 'Nama Pegawai', value: `${props.targetEmployee?.name} (${props.targetEmployee?.nip})` },
            { label: 'Unit / Bagian', value: `${props.targetEmployee?.division || '-'} • ${props.targetEmployee?.position || '-'}` },
            { label: 'Periode', value: `${filterForm.start_date} s/d ${filterForm.end_date}` },
            { label: 'Sumber Laporan', value: sumberLabel },
        ],
        legendItems: legendItems,
        columns: [
            { header: 'No', width: '26px', align: 'center', render: (row, idx) => idx + 1 },
            { header: 'Hari', width: '52px', field: 'day_name' },
            { header: 'Tanggal', width: '68px', field: 'formatted_date', align: 'center' },
            { header: 'Shift', width: '64px', field: 'shift_name' },
            {
                header: 'Masuk',
                width: '50px',
                align: 'center',
                render: (row) =>
                    row.is_late
                        ? `<span style="color: #b45309; font-weight: 700;">${row.check_in}</span>`
                        : row.check_in || '-',
            },
            {
                header: 'Pulang',
                width: '50px',
                align: 'center',
                render: (row) =>
                    row.is_early_leave
                        ? `<span style="color: #be123c; font-weight: 700;">${row.check_out}</span>`
                        : row.check_out || '-',
            },
            {
                header: 'Lembur',
                width: '48px',
                align: 'center',
                render: (row) =>
                    row.overtime_duration && row.overtime_duration !== '-'
                        ? `<span style="color: #047857; font-weight: 700;">${row.overtime_duration}</span>`
                        : '-',
            },
            {
                header: 'Status',
                width: '88px',
                render: (row) => {
                    let col = '#334155';
                    if (row.badge_type === 'weekend') col = '#be123c';
                    else if (row.badge_type === 'libur_nasional') col = '#b45309';
                    else if (row.badge_type === 'lembur_libur') col = '#047857';
                    else if (row.badge_type === 'alpha') col = '#dc2626';
                    else if (row.badge_type === 'belum_berjalan') col = '#475569';
                    else if (row.badge_type === 'terlambat') col = '#b45309';
                    else if (row.badge_type === 'hadir') col = '#15803d';
                    return `<span style="color: ${col}; font-weight: 600;">${row.status || '-'}</span>`;
                },
            },
            {
                header: 'Keterangan',
                field: 'notes',
                render: (row) => {
                    if (row.badge_type === 'belum_berjalan') {
                        return '<span style="color: #475569; font-style: italic;">Belum Berjalan</span>';
                    }
                    return row.notes || '-';
                },
            },
        ],
        data: dataToExport,
    });
}
</script>

<template>
    <Head title="Laporan Absensi Karyawan - eSDM KFPB" />

    <div class="space-y-5 max-w-7xl mx-auto px-3 sm:px-6 pt-1 pb-6">
        <Toast position="top-right" />

        <!-- ================= TOP PAGE HEADER (FLAT ON CANVAS) ================= -->
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
            <div class="space-y-1">
                <nav aria-label="Breadcrumb" class="flex items-center gap-2 text-xs font-medium text-slate-600">
                    <Link href="/absensi" class="hover:text-[#005BAC] flex items-center gap-1 py-0.5">
                        <i class="pi pi-calendar-check text-[11px]" aria-hidden="true" />
                        <span>Presensi</span>
                    </Link>
                    <span aria-hidden="true">/</span>
                    <span class="text-slate-900 font-semibold">Laporan Absensi Karyawan</span>
                </nav>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight flex flex-wrap items-center gap-2">
                    <span>Laporan &amp; Riwayat Absensi</span>
                    <span
                        v-if="isAdmin"
                        class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-blue-50 text-[#005BAC] border border-blue-200"
                    >
                        Akses Administrator / HRD
                    </span>
                    <span
                        v-else-if="isSupervisor"
                        class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-blue-50 text-[#005BAC] border border-blue-200"
                    >
                        Akses Atasan
                    </span>
                </h1>
                <p class="text-xs sm:text-sm text-slate-600">
                    Rekap presensi kehadiran pegawai Kimia Farma Plant Banjaran.
                </p>
            </div>

            <Link
                href="/absensi"
                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 min-h-[44px] rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 font-semibold text-xs sm:text-sm transition-colors shrink-0 self-start sm:self-auto"
            >
                <i class="pi pi-arrow-left text-xs" aria-hidden="true" />
                <span>Kembali ke Presensi</span>
            </Link>
        </div>

        <!-- ================= 6 KARTU STATISTIK RINGKAS ================= -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <!-- 1. Total Hari -->
            <div class="bg-white p-3.5 rounded-xl border border-slate-200/90 shadow-xs flex items-center justify-between gap-2">
                <div class="min-w-0">
                    <span class="text-[11px] font-bold text-slate-600 uppercase tracking-wider block truncate">Total Hari</span>
                    <span class="text-xl font-bold font-mono text-slate-900">{{ statistics.total }}</span>
                </div>
                <div class="size-9 rounded-lg bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-700 shrink-0">
                    <i class="pi pi-calendar text-sm" aria-hidden="true" />
                </div>
            </div>

            <!-- 2. Hadir -->
            <div class="bg-white p-3.5 rounded-xl border border-slate-200/90 shadow-xs flex items-center justify-between gap-2">
                <div class="min-w-0">
                    <span class="text-[11px] font-bold text-emerald-800 uppercase tracking-wider block truncate">Hadir</span>
                    <span class="text-xl font-bold font-mono text-emerald-800">{{ statistics.hadir }}</span>
                </div>
                <div class="size-9 rounded-lg bg-emerald-50 border border-emerald-200 flex items-center justify-center text-emerald-800 shrink-0">
                    <i class="pi pi-check-circle text-sm" aria-hidden="true" />
                </div>
            </div>

            <!-- 3. Terlambat -->
            <div class="bg-white p-3.5 rounded-xl border border-slate-200/90 shadow-xs flex items-center justify-between gap-2">
                <div class="min-w-0">
                    <span class="text-[11px] font-bold text-amber-800 uppercase tracking-wider block truncate">Terlambat</span>
                    <span class="text-xl font-bold font-mono text-amber-800">{{ statistics.terlambat }}</span>
                </div>
                <div class="size-9 rounded-lg bg-amber-50 border border-amber-200 flex items-center justify-center text-amber-800 shrink-0">
                    <i class="pi pi-clock text-sm" aria-hidden="true" />
                </div>
            </div>

            <!-- 4. Izin / Cuti / Sakit -->
            <div class="bg-white p-3.5 rounded-xl border border-slate-200/90 shadow-xs flex items-center justify-between gap-2">
                <div class="min-w-0">
                    <span class="text-[11px] font-bold text-[#005BAC] uppercase tracking-wider block truncate">Izin / Cuti</span>
                    <span class="text-xl font-bold font-mono text-[#004280]">{{ statistics.izin }}</span>
                </div>
                <div class="size-9 rounded-lg bg-blue-50 border border-blue-200 flex items-center justify-center text-[#005BAC] shrink-0">
                    <i class="pi pi-file-edit text-sm" aria-hidden="true" />
                </div>
            </div>

            <!-- 5. Lembur -->
            <div class="bg-white p-3.5 rounded-xl border border-slate-200/90 shadow-xs flex items-center justify-between gap-2">
                <div class="min-w-0">
                    <span class="text-[11px] font-bold text-teal-700 uppercase tracking-wider block truncate">Lembur</span>
                    <span class="text-xl font-bold font-mono text-teal-800">{{ statistics.lembur }}</span>
                </div>
                <div class="size-9 rounded-lg bg-teal-50 border border-teal-200 flex items-center justify-center text-teal-700 shrink-0">
                    <i class="pi pi-bolt text-sm" aria-hidden="true" />
                </div>
            </div>

            <!-- 6. Tidak Hadir (Alpha) -->
            <div class="bg-white p-3.5 rounded-xl border border-slate-200/90 shadow-xs flex items-center justify-between gap-2">
                <div class="min-w-0">
                    <span class="text-[11px] font-bold text-rose-800 uppercase tracking-wider block truncate">Alpha</span>
                    <span class="text-xl font-bold font-mono text-rose-800">{{ statistics.alpha }}</span>
                </div>
                <div class="size-9 rounded-lg bg-rose-50 border border-rose-200 flex items-center justify-center text-rose-800 shrink-0">
                    <i class="pi pi-times-circle text-sm" aria-hidden="true" />
                </div>
            </div>
        </div>

        <!-- ================= PANEL FILTER PERIODE & PEGAWAI ================= -->
        <section
            aria-label="Filter Periode dan Pegawai"
            class="bg-white rounded-2xl border border-slate-200 border-t-4 border-t-[#005BAC] shadow-sm overflow-hidden"
        >
            <div class="px-5 sm:px-6 py-4 border-b border-slate-200/80 flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-3">
                    <div class="size-9 rounded-xl bg-blue-50 text-[#005BAC] flex items-center justify-center shrink-0 border border-blue-200/60">
                        <i class="pi pi-sliders-h text-base" aria-hidden="true" />
                    </div>
                    <div>
                        <h2 class="font-bold text-sm sm:text-base text-slate-900 tracking-tight">
                            Filter Periode &amp; Pegawai
                        </h2>
                        <p class="text-xs text-slate-600">
                            Pilih rentang tanggal dan sumber pencatatan presensi yang ingin ditampilkan.
                        </p>
                    </div>
                </div>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 text-xs font-mono font-semibold border border-slate-200">
                    <i class="pi pi-calendar text-[11px] text-[#005BAC]" aria-hidden="true" />
                    <span>{{ activePeriodLabel }}</span>
                </span>
            </div>

            <form @submit.prevent="applyFilter" class="p-5 sm:p-6 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                    <!-- Pegawai -->
                    <div class="min-w-0">
                        <span id="filter-nip-label" class="block text-xs font-bold text-slate-700 mb-1.5">
                            Nama Pegawai
                        </span>
                        <Select
                            v-if="employeeOptions.length > 1"
                            v-model="filterForm.nip"
                            :options="employeeOptions"
                            optionLabel="name"
                            optionValue="nip"
                            aria-labelledby="filter-nip-label"
                            placeholder="Pilih Pegawai"
                            filter
                            filterPlaceholder="Cari NIP atau Nama..."
                            class="w-full"
                        />
                        <div
                            v-else
                            class="min-h-[44px] px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-xs sm:text-sm font-semibold text-slate-800 flex items-center truncate"
                        >
                            {{ targetEmployee.name }} ({{ targetEmployee.nip }})
                        </div>
                    </div>

                    <!-- Tanggal Awal -->
                    <div class="min-w-0">
                        <label for="filter-start-date" class="block text-xs font-bold text-slate-700 mb-1.5">
                            Tanggal Awal
                        </label>
                        <DatePicker
                            v-model="selectedStartDate"
                            inputId="filter-start-date"
                            dateFormat="dd/mm/yy"
                            placeholder="dd/mm/yyyy"
                            showIcon
                            fluid
                            :manualInput="false"
                            :pt="{
                                pcInputText: { root: { name: 'start_date', autocomplete: 'off' } },
                                dropdown: { 'aria-label': 'Buka kalender tanggal awal' },
                                panel: { 'aria-label': 'Pilih tanggal awal periode' },
                            }"
                        />
                    </div>

                    <!-- Tanggal Akhir -->
                    <div class="min-w-0">
                        <label for="filter-end-date" class="block text-xs font-bold text-slate-700 mb-1.5">
                            Tanggal Akhir
                        </label>
                        <DatePicker
                            v-model="selectedEndDate"
                            inputId="filter-end-date"
                            dateFormat="dd/mm/yy"
                            placeholder="dd/mm/yyyy"
                            showIcon
                            fluid
                            :manualInput="false"
                            :pt="{
                                pcInputText: { root: { name: 'end_date', autocomplete: 'off' } },
                                dropdown: { 'aria-label': 'Buka kalender tanggal akhir' },
                                panel: { 'aria-label': 'Pilih tanggal akhir periode' },
                            }"
                        />
                    </div>

                    <!-- Sumber Presensi -->
                    <div class="min-w-0">
                        <span id="filter-sumber-label" class="block text-xs font-bold text-slate-700 mb-1.5">
                            Sumber Presensi
                        </span>
                        <Select
                            v-model="filterForm.sumber"
                            :options="sumberOptions"
                            optionLabel="label"
                            optionValue="value"
                            aria-labelledby="filter-sumber-label"
                            placeholder="Pilih Sumber"
                            class="w-full"
                        />
                    </div>
                </div>

                <!-- Baris Aksi Filter -->
                <div class="pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-slate-600">
                        Menampilkan data pegawai:
                        <span class="font-bold text-slate-900">{{ targetEmployee.name }}</span>{{ ' ' }}<span class="font-mono text-slate-700">({{ targetEmployee.nip }})</span>
                        <span v-if="targetEmployee.division"> &bull; {{ targetEmployee.division }}</span>
                    </p>
                    <div class="flex flex-wrap items-center gap-2.5 w-full sm:w-auto justify-end">
                        <Button
                            type="button"
                            label="Reset Filter"
                            icon="pi pi-refresh"
                            severity="secondary"
                            outlined
                            size="small"
                            :disabled="isLoadingReport"
                            class="min-h-[44px] !text-slate-700 text-xs sm:text-sm flex-1 sm:flex-initial"
                            @click="resetFilter"
                        />
                        <Button
                            type="submit"
                            label="Filter Data"
                            icon="pi pi-filter"
                            size="small"
                            :loading="isLoadingReport"
                            :disabled="isLoadingReport"
                            class="!bg-[#005BAC] hover:!bg-[#004280] !border-[#005BAC] !text-white min-h-[44px] px-5 text-xs sm:text-sm font-semibold flex-1 sm:flex-initial"
                        />
                    </div>
                </div>
            </form>
        </section>

        <!-- ================= LEGENDA STATUS WARNA BARIS ================= -->
        <div
            aria-label="Keterangan warna baris tabel kehadiran"
            class="bg-white px-4 sm:px-5 py-3 rounded-xl border border-slate-200/90 shadow-xs flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-slate-700"
        >
            <span class="font-bold text-slate-800 text-[11px] uppercase tracking-wider flex items-center gap-1.5 mr-1">
                <i class="pi pi-info-circle text-[#005BAC]" aria-hidden="true" />
                <span>Keterangan Status:</span>
            </span>
            <div class="flex items-center gap-2">
                <span class="w-4 h-4 rounded border border-rose-300 bg-[#fee2e2] inline-block shrink-0" aria-hidden="true" />
                <span class="font-medium text-slate-700">Sabtu/Minggu (Libur Mingguan)</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="w-4 h-4 rounded border border-amber-300 bg-[#fef3c7] inline-block shrink-0" aria-hidden="true" />
                <span class="font-medium text-slate-700">Libur Nasional</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="w-4 h-4 rounded border border-emerald-300 bg-[#dcfce7] inline-block shrink-0" aria-hidden="true" />
                <span class="font-medium text-slate-700">Lembur/Masuk di Hari Libur</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="w-4 h-4 rounded border border-slate-400 bg-[#e2e8f0] inline-block shrink-0" aria-hidden="true" />
                <span class="font-medium text-slate-700">Tidak Hadir (Alpha)</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="w-4 h-4 rounded border border-slate-300 bg-white inline-block shrink-0" aria-hidden="true" />
                <span class="font-medium text-slate-700">Belum Berjalan</span>
            </div>
        </div>

        <!-- ================= CARD TABEL LAPORAN KEHADIRAN ================= -->
        <section
            aria-label="Daftar Kehadiran Harian"
            class="bg-white rounded-2xl border border-slate-200 border-t-4 border-t-[#005BAC] shadow-sm overflow-hidden"
        >
            <!-- Card Header -->
            <div class="px-5 sm:px-6 py-4 border-b border-slate-200/80 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="size-9 rounded-xl bg-blue-50 text-[#005BAC] flex items-center justify-center shrink-0 border border-blue-200/60">
                        <i class="pi pi-calendar text-base" aria-hidden="true" />
                    </div>
                    <div>
                        <h2 class="font-bold text-sm sm:text-base text-slate-900 tracking-tight">
                            Daftar Kehadiran Harian
                        </h2>
                        <p class="text-xs text-slate-600">
                            Menampilkan {{ filteredAttendances.length }} dari {{ attendances.length }} hari periode {{ activePeriodLabel }} (urut tanggal 1 s/d akhir).
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        type="button"
                        label="Muat Ulang"
                        icon="pi pi-refresh"
                        size="small"
                        severity="secondary"
                        outlined
                        :loading="isLoadingReport"
                        :disabled="isLoadingReport"
                        class="min-h-[44px] text-xs !text-slate-700"
                        @click="refreshReport"
                    />
                    <Button
                        type="button"
                        label="Export PDF"
                        icon="pi pi-file-pdf"
                        severity="danger"
                        outlined
                        size="small"
                        class="min-h-[44px] text-xs !text-rose-700 !border-rose-300 hover:!bg-rose-50"
                        @click="handleExportPdf"
                    />
                </div>
            </div>

            <!-- Bar Filter Pencarian Client-Side -->
            <div v-if="attendances.length > 0" class="px-5 sm:px-6 pt-4 pb-1 grid grid-cols-1 sm:grid-cols-12 gap-3">
                <div class="sm:col-span-7">
                    <label for="attendance-instant-search" class="sr-only">
                        Cari hari, tanggal, shift, jam, atau status kehadiran
                    </label>
                    <IconField class="w-full">
                        <InputIcon class="pi pi-search text-slate-600" aria-hidden="true" />
                        <InputText
                            id="attendance-instant-search"
                            v-model="instantSearch"
                            name="instant_search"
                            placeholder="Cari hari, tanggal, shift, jam, status..."
                            class="w-full min-h-[44px] text-sm"
                        />
                    </IconField>
                </div>
                <div class="sm:col-span-5">
                    <span id="attendance-status-filter-label" class="sr-only">Filter status kehadiran</span>
                    <Select
                        v-model="statusFilter"
                        :options="statusFilterOptions"
                        optionLabel="label"
                        optionValue="value"
                        aria-labelledby="attendance-status-filter-label"
                        placeholder="-- Semua Status --"
                        class="w-full"
                    />
                </div>
            </div>

            <!-- Isi Card -->
            <div class="p-5 sm:p-6">
                <!-- Loading State Eksplisit -->
                <div
                    v-if="isLoadingReport"
                    role="status"
                    aria-live="polite"
                    class="py-10 px-4 text-center space-y-3"
                >
                    <i class="pi pi-spin motion-reduce:animate-none pi-spinner text-2xl text-[#005BAC]" aria-hidden="true" />
                    <p class="text-sm font-medium text-slate-700">Memuat data rekapitulasi absensi...</p>
                </div>

                <!-- Empty State Data Periode Kosong -->
                <div v-else-if="attendances.length === 0" class="text-center py-10 px-4 space-y-3">
                    <div class="size-12 rounded-2xl bg-slate-100 text-slate-600 flex items-center justify-center mx-auto">
                        <i class="pi pi-inbox text-xl" aria-hidden="true" />
                    </div>
                    <div class="space-y-1 max-w-md mx-auto">
                        <p class="text-sm font-bold text-slate-900">Belum Ada Data Presensi pada Periode Ini</p>
                        <p class="text-xs text-slate-600">
                            Coba pilih rentang tanggal lain atau kembalikan ke periode bulan berjalan.
                        </p>
                    </div>
                    <Button
                        type="button"
                        label="Reset ke Bulan Berjalan"
                        icon="pi pi-refresh"
                        size="small"
                        class="!bg-[#005BAC] hover:!bg-[#004280] !border-[#005BAC] !text-white min-h-[44px]"
                        @click="resetFilter"
                    />
                </div>

                <!-- Empty State Hasil Pencarian Instan -->
                <div v-else-if="filteredAttendances.length === 0" class="text-center py-10 px-4 space-y-3">
                    <div class="size-12 rounded-2xl bg-slate-100 text-slate-600 flex items-center justify-center mx-auto">
                        <i class="pi pi-search text-xl" aria-hidden="true" />
                    </div>
                    <div class="space-y-1 max-w-md mx-auto">
                        <p class="text-sm font-bold text-slate-900">Tidak Ada Data yang Cocok dengan Pencarian</p>
                        <p class="text-xs text-slate-600">
                            Ubah kata kunci pencarian atau pilih "Semua Status" untuk menampilkan kembali seluruh baris.
                        </p>
                    </div>
                    <Button
                        type="button"
                        label="Reset Filter Tabel"
                        icon="pi pi-filter-slash"
                        severity="secondary"
                        outlined
                        size="small"
                        class="min-h-[44px] !text-slate-700"
                        @click="clearTableFilters"
                    />
                </div>

                <!-- Data View -->
                <template v-else>
                    <!-- Desktop DataTable (>= lg: 1024px) -->
                    <div class="hidden lg:block">
                        <DataTable
                            v-model:first="tableFirst"
                            v-model:rows="tableRows"
                            :value="filteredAttendances"
                            dataKey="id"
                            paginator
                            :rowsPerPageOptions="[15, 31, 50, 100]"
                            sortMode="multiple"
                            removableSort
                            responsiveLayout="scroll"
                            :rowClass="getRowClass"
                            class="text-sm"
                            :pt="{
                                bodyCell: { class: '!py-2' },
                            }"
                        >
                            <!-- Kolom No -->
                            <Column header="No" style="width: 3.25rem" class="text-center">
                                <template #body="{ index }">
                                    <span class="font-mono text-slate-600 text-xs">{{ tableFirst + index + 1 }}</span>
                                </template>
                            </Column>

                            <!-- Hari & Tanggal -->
                            <Column field="date" header="Hari &amp; Tanggal" sortable style="min-width: 9rem">
                                <template #body="{ data }">
                                    <div>
                                        <span class="font-bold text-slate-900 text-xs">{{ data.day_name }}</span>
                                        <span class="text-slate-600 font-mono text-[11px] block">{{ data.formatted_date }}</span>
                                    </div>
                                </template>
                            </Column>

                            <!-- Shift Kerja -->
                            <Column field="shift_name" header="Shift Kerja" sortable style="min-width: 7rem">
                                <template #body="{ data }">
                                    <span class="px-2 py-0.5 rounded-md bg-slate-100 border border-slate-200/80 text-slate-800 text-xs font-semibold whitespace-nowrap">
                                        {{ data.shift_name }}
                                    </span>
                                </template>
                            </Column>

                            <!-- Jam Masuk -->
                            <Column field="check_in" header="Jam Masuk" sortable style="min-width: 6.5rem" class="text-center">
                                <template #body="{ data }">
                                    <div class="font-mono text-xs">
                                        <span :class="data.is_late ? 'text-amber-800 font-bold' : 'text-slate-900 font-semibold'">
                                            {{ data.check_in }}
                                        </span>
                                        <span v-if="data.is_late" class="block text-[11px] text-amber-800 font-sans font-semibold">
                                            (Terlambat)
                                        </span>
                                    </div>
                                </template>
                            </Column>

                            <!-- Jam Pulang -->
                            <Column field="check_out" header="Jam Pulang" sortable style="min-width: 6.5rem" class="text-center">
                                <template #body="{ data }">
                                    <div class="font-mono text-xs">
                                        <span :class="data.is_early_leave ? 'text-rose-800 font-bold' : 'text-slate-900 font-semibold'">
                                            {{ data.check_out }}
                                        </span>
                                        <span v-if="data.is_early_leave" class="block text-[11px] text-rose-800 font-sans font-semibold">
                                            (P. Cepat)
                                        </span>
                                    </div>
                                </template>
                            </Column>

                            <!-- Lembur -->
                            <Column field="overtime_duration" header="Lembur" sortable style="min-width: 5.5rem" class="text-center">
                                <template #body="{ data }">
                                    <span
                                        v-if="data.overtime_duration && data.overtime_duration !== '-'"
                                        class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-800 border border-emerald-200 font-mono text-xs font-bold whitespace-nowrap"
                                    >
                                        {{ data.overtime_duration }}
                                    </span>
                                    <span v-else class="text-slate-600 text-xs">-</span>
                                </template>
                            </Column>

                            <!-- Status -->
                            <Column field="status" header="Status" sortable style="min-width: 9rem">
                                <template #body="{ data }">
                                    <Tag
                                        :value="data.status"
                                        :severity="data.severity || 'info'"
                                        class="text-xs font-semibold whitespace-nowrap"
                                    />
                                </template>
                            </Column>

                            <!-- Keterangan -->
                            <Column field="notes" header="Keterangan" style="max-width: 15rem">
                                <template #body="{ data }">
                                    <div class="truncate text-slate-700 text-xs" :title="data.notes">
                                        {{ data.notes }}
                                    </div>
                                </template>
                            </Column>
                        </DataTable>
                    </div>

                    <!-- Mobile & Tablet Responsive Cards (< lg: < 1024px) -->
                    <div class="lg:hidden space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <article
                                v-for="(item, idx) in paginatedCardAttendances"
                                :key="item.id"
                                :class="['p-4 rounded-xl border flex flex-col justify-between gap-2.5', getCardClass(item)]"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <h3 class="font-bold text-sm text-slate-900 leading-snug">
                                            {{ item.day_name }}, {{ item.formatted_date }}
                                        </h3>
                                        <p class="text-xs font-semibold text-slate-700 mt-0.5">
                                            <span class="font-mono text-slate-600">#{{ (responsivePage - 1) * responsivePerPage + idx + 1 }}</span>
                                            <span aria-hidden="true"> &bull; </span>
                                            <span>Shift: {{ item.shift_name }}</span>
                                        </p>
                                    </div>
                                    <Tag
                                        :value="item.status"
                                        :severity="item.severity || 'info'"
                                        class="text-xs font-semibold shrink-0"
                                    />
                                </div>

                                <div class="grid grid-cols-3 gap-2 py-2 border-y border-slate-300/70 text-xs">
                                    <div>
                                        <span class="text-[11px] font-bold uppercase text-slate-600 block">Masuk</span>
                                        <span :class="item.is_late ? 'font-mono font-bold text-amber-800' : 'font-mono font-bold text-slate-900'">
                                            {{ item.check_in }}
                                        </span>
                                        <span v-if="item.is_late" class="block text-[11px] text-amber-800 font-semibold">
                                            Terlambat
                                        </span>
                                    </div>
                                    <div>
                                        <span class="text-[11px] font-bold uppercase text-slate-600 block">Pulang</span>
                                        <span :class="item.is_early_leave ? 'font-mono font-bold text-rose-800' : 'font-mono font-bold text-slate-900'">
                                            {{ item.check_out }}
                                        </span>
                                        <span v-if="item.is_early_leave" class="block text-[11px] text-rose-800 font-semibold">
                                            P. Cepat
                                        </span>
                                    </div>
                                    <div>
                                        <span class="text-[11px] font-bold uppercase text-slate-600 block">Lembur</span>
                                        <span
                                            v-if="item.overtime_duration && item.overtime_duration !== '-'"
                                            class="font-mono font-bold text-emerald-800"
                                        >
                                            {{ item.overtime_duration }}
                                        </span>
                                        <span v-else class="font-mono text-slate-600">-</span>
                                    </div>
                                </div>

                                <p class="text-xs text-slate-700 leading-relaxed">
                                    <span class="font-semibold text-slate-800">Keterangan:</span>
                                    {{ item.notes || '-' }}
                                </p>
                            </article>
                        </div>

                        <!-- Paginasi Mobile & Tablet -->
                        <div
                            v-if="responsiveTotalPages > 1"
                            class="pt-2 flex items-center justify-between gap-2 border-t border-slate-200"
                        >
                            <Button
                                type="button"
                                label="Sebelumnya"
                                icon="pi pi-chevron-left"
                                size="small"
                                severity="secondary"
                                outlined
                                :disabled="responsivePage <= 1"
                                class="min-h-[44px] text-xs !text-slate-700"
                                @click="responsivePage--"
                            />
                            <span class="text-xs font-mono text-slate-700">
                                Hal {{ responsivePage }} / {{ responsiveTotalPages }}
                            </span>
                            <Button
                                type="button"
                                label="Berikutnya"
                                icon="pi pi-chevron-right"
                                iconPos="right"
                                size="small"
                                severity="secondary"
                                outlined
                                :disabled="responsivePage >= responsiveTotalPages"
                                class="min-h-[44px] text-xs !text-slate-700"
                                @click="responsivePage++"
                            />
                        </div>
                    </div>
                </template>
            </div>
        </section>
    </div>
</template>

<style scoped>
:deep(.p-select),
:deep(.p-datepicker-input) {
    min-height: 44px;
}

:deep(.p-datepicker-dropdown) {
    min-width: 44px;
    min-height: 44px;
}

:deep(.p-paginator button),
:deep(.p-paginator-page),
:deep(.p-paginator-first),
:deep(.p-paginator-prev),
:deep(.p-paginator-next),
:deep(.p-paginator-last) {
    min-width: 44px;
    min-height: 44px;
}

:deep(.p-paginator .p-paginator-page.p-paginator-page-selected) {
    background: #e6f0f8;
    color: #005BAC;
    font-weight: 700;
}

:deep(.p-datatable-tbody > tr.row-weekend) {
    background-color: #fee2e2 !important;
}
:deep(.p-datatable-tbody > tr.row-libur-nasional) {
    background-color: #fef3c7 !important;
}
:deep(.p-datatable-tbody > tr.row-lembur-libur) {
    background-color: #dcfce7 !important;
}
:deep(.p-datatable-tbody > tr.row-alpha) {
    background-color: #e2e8f0 !important;
}
:deep(.p-datatable-tbody > tr.row-belum-berjalan) {
    background-color: #ffffff !important;
    color: #475569 !important;
}
:deep(.p-datatable-tbody > tr.row-weekend:hover) {
    background-color: #fecdd3 !important;
}
:deep(.p-datatable-tbody > tr.row-libur-nasional:hover) {
    background-color: #fde68a !important;
}
:deep(.p-datatable-tbody > tr.row-lembur-libur:hover) {
    background-color: #bbf7d0 !important;
}
:deep(.p-datatable-tbody > tr.row-alpha:hover) {
    background-color: #cbd5e1 !important;
}
</style>
