<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, reactive, computed } from 'vue';
import Card from 'primevue/card';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
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

// State Form Filter Backend (Default Awal Bulan s/d Akhir Bulan Berjalan)
const now = new Date();
const currentY = now.getFullYear();
const currentM = String(now.getMonth() + 1).padStart(2, '0');
const currentLastDay = new Date(currentY, now.getMonth() + 1, 0).getDate();
const defaultStartDate = `${currentY}-${currentM}-01`;
const defaultEndDate = `${currentY}-${currentM}-${String(currentLastDay).padStart(2, '0')}`;

const filterForm = reactive({
    nip: props.filters.nip || props.targetEmployee?.nip || '',
    start_date: props.filters.start_date || defaultStartDate,
    end_date: props.filters.end_date || defaultEndDate,
    sumber: props.filters.sumber || 'all',
});

// State Pencarian Instan dan Seleksi Tabel
const instantSearch = ref('');
const statusFilter = ref(null);
const selectedRows = ref([]);

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

// Computed Filter Client-Side Instan
const filteredAttendances = computed(() => {
    const list = props.attendances || [];
    return list.filter((item) => {
        const q = instantSearch.value.toLowerCase().trim();
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
        if (statusFilter.value === 'Hadir Tepat Waktu') {
            matchStatus = item.status === 'Hadir Tepat Waktu';
        } else if (statusFilter.value === 'Terlambat') {
            matchStatus = item.status === 'Terlambat' || item.is_late;
        } else if (statusFilter.value === 'Izin') {
            matchStatus = item.badge_type === 'izin';
        } else if (statusFilter.value === 'Lembur') {
            matchStatus = (item.overtime_duration && item.overtime_duration !== '-') || item.badge_type === 'lembur_libur';
        } else if (statusFilter.value === 'Libur') {
            matchStatus = ['weekend', 'libur_nasional', 'lembur_libur'].includes(item.badge_type);
        } else if (statusFilter.value === 'Alpha') {
            matchStatus = item.badge_type === 'alpha';
        }

        return matchSearch && matchStatus;
    });
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

function applyFilter() {
    const targetUrl = typeof window !== 'undefined' ? window.location.pathname : '/absensi/laporan';
    router.get(targetUrl, {
        nip: filterForm.nip,
        start_date: filterForm.start_date,
        end_date: filterForm.end_date,
        sumber: filterForm.sumber,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
}

function resetFilter() {
    filterForm.nip = props.targetEmployee?.nip || '';
    const n = new Date();
    const y = n.getFullYear();
    const m = String(n.getMonth() + 1).padStart(2, '0');
    const lastDay = new Date(y, n.getMonth() + 1, 0).getDate();
    filterForm.start_date = `${y}-${m}-01`;
    filterForm.end_date = `${y}-${m}-${String(lastDay).padStart(2, '0')}`;
    filterForm.sumber = 'all';
    instantSearch.value = '';
    statusFilter.value = null;
    applyFilter();
}

function handleExportPdf() {
    const list = filteredAttendances.value || [];
    if (list.length === 0) {
        alert('Tidak ada data absensi yang dapat diekspor.');
        return;
    }

    // Urutkan tanggal dari hari pertama s/d terakhir (kronologis) untuk lembar cetak resmi
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
                render: (row) => row.is_late
                    ? `<span style="color: #b45309; font-weight: 700;">${row.check_in}</span>`
                    : (row.check_in || '-')
            },
            {
                header: 'Pulang',
                width: '50px',
                align: 'center',
                render: (row) => row.is_early_leave
                    ? `<span style="color: #be123c; font-weight: 700;">${row.check_out}</span>`
                    : (row.check_out || '-')
            },
            {
                header: 'Lembur',
                width: '48px',
                align: 'center',
                render: (row) => (row.overtime_duration && row.overtime_duration !== '-')
                    ? `<span style="color: #047857; font-weight: 700;">${row.overtime_duration}</span>`
                    : '-'
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
                    else if (row.badge_type === 'belum_berjalan') col = '#64748b';
                    else if (row.badge_type === 'terlambat') col = '#b45309';
                    else if (row.badge_type === 'hadir') col = '#15803d';
                    return `<span style="color: ${col}; font-weight: 600;">${row.status || '-'}</span>`;
                }
            },
            {
                header: 'Keterangan',
                field: 'notes',
                render: (row) => {
                    if (row.badge_type === 'belum_berjalan') {
                        return '<span style="color: #64748b; font-style: italic;">Belum Berjalan</span>';
                    }
                    return row.notes || '-';
                }
            },
        ],
        data: dataToExport,
    });
}
</script>

<template>
    <Head title="Laporan Absensi Karyawan - eSDM KFPB" />

    <div class="space-y-5 max-w-7xl mx-auto px-3 sm:px-6 py-2">

        <!-- ================= TOP HEADER BREADCRUMB & TITLE ================= -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="space-y-1">
                <div class="flex items-center gap-2 text-xs font-medium text-slate-500">
                    <Link href="/absensi" class="hover:text-[#005BAC] flex items-center gap-1">
                        <i class="pi pi-calendar-check text-[11px]" />
                        <span>Presensi</span>
                    </Link>
                    <span>/</span>
                    <span class="text-slate-800 font-semibold">Laporan Absensi Karyawan</span>
                </div>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
                    <span>Laporan &amp; Riwayat Absensi</span>
                    <span v-if="isAdmin" class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200">
                        Akses Administrator / HRD
                    </span>
                    <span v-else-if="isSupervisor" class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-blue-50 text-[#005BAC] border border-blue-200">
                        Akses Atasan
                    </span>
                </h1>
                <p class="text-xs sm:text-sm text-slate-500">
                    Rekap presensi kehadiran pegawai Kimia Farma Plant Banjaran.
                </p>
            </div>

            <!-- Tombol Aksi Cepat Header -->
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                
                <Link
                    href="/absensi"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-200 text-slate-700 bg-slate-50 hover:bg-slate-100 font-semibold text-xs transition-colors shadow-2xs"
                >
                    <i class="pi pi-arrow-left text-xs" />
                    <span>Presensi Harian</span>
                </Link>
            </div>
        </div>

        <!-- ================= 6 KARTU STATISTIK RINGKAS ================= -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <!-- Total Hari -->
            <div class="bg-white p-3.5 rounded-xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Total Hari</span>
                    <span class="text-xl font-bold font-mono text-slate-800">{{ statistics.total }}</span>
                </div>
                <div class="size-8 rounded-lg bg-slate-100 flex items-center justify-center text-slate-600">
                    <i class="pi pi-calendar text-sm" />
                </div>
            </div>


            <!-- Izin / Cuti / Sakit -->
            <div class="bg-white p-3.5 rounded-xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-bold text-blue-600 uppercase tracking-wider block">Izin / Cuti</span>
                    <span class="text-xl font-bold font-mono text-blue-700">{{ statistics.izin }}</span>
                </div>
                <div class="size-8 rounded-lg bg-blue-50 flex items-center justify-center text-blue-600 border border-blue-100">
                    <i class="pi pi-file-edit text-sm" />
                </div>
            </div>

            <!-- Lembur -->
            <div class="bg-white p-3.5 rounded-xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-bold text-teal-600 uppercase tracking-wider block">Lembur</span>
                    <span class="text-xl font-bold font-mono text-teal-700">{{ statistics.lembur }}</span>
                </div>
                <div class="size-8 rounded-lg bg-teal-50 flex items-center justify-center text-teal-600 border border-teal-100">
                    <i class="pi pi-bolt text-sm" />
                </div>
            </div>

        </div>

        <!-- ================= PANEL FILTER PERIODE & PEGAWAI ================= -->
        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-sm">
            <form @submit.prevent="applyFilter" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3 items-end">
                <!-- Dropdown Pilih Pegawai (Aktif Jika Atasan / Admin Memiliki Pilihan) -->
                <div :class="employeeOptions.length > 1 ? 'col-span-1 sm:col-span-2 md:col-span-1' : ''">
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Nama Pegawai</label>
                    <Select
                        v-if="employeeOptions.length > 1"
                        v-model="filterForm.nip"
                        :options="employeeOptions"
                        optionLabel="name"
                        optionValue="nip"
                        placeholder="Pilih Pegawai"
                        filter
                        filterPlaceholder="Cari NIP atau Nama..."
                        class="w-full text-xs"
                    />
                    <div v-else class="px-3 py-2 rounded-xl border border-slate-200 bg-slate-50 text-xs font-semibold text-slate-700 truncate">
                        {{ targetEmployee.name }} ({{ targetEmployee.nip }})
                    </div>
                </div>

                <!-- Tanggal Awal -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Tanggal Awal</label>
                    <input
                        v-model="filterForm.start_date"
                        type="date"
                        class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs text-slate-800 bg-white focus:outline-none focus:ring-2 focus:ring-[#005BAC]/30 focus:border-[#005BAC]"
                    />
                </div>

                <!-- Tanggal Akhir -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Tanggal Akhir</label>
                    <input
                        v-model="filterForm.end_date"
                        type="date"
                        class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs text-slate-800 bg-white focus:outline-none focus:ring-2 focus:ring-[#005BAC]/30 focus:border-[#005BAC]"
                    />
                </div>

                <!-- Sumber Laporan -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Sumber Presensi</label>
                    <Select
                        v-model="filterForm.sumber"
                        :options="sumberOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Pilih Sumber"
                        class="w-full text-xs"
                    />
                </div>

                <!-- Tombol Aksi Filter & Reset -->
                <div class="flex items-center gap-2">
                    <Button
                        type="submit"
                        label="Filter Data"
                        icon="pi pi-filter"
                        size="small"
                        class="flex-1"
                    />
                    <Button
                        type="button"
                        icon="pi pi-refresh"
                        severity="secondary"
                        outlined
                        size="small"
                        title="Reset Filter"
                        @click="resetFilter"
                    />
                </div>
            </form>
        </div>

        <!-- ================= LEGENDA STATUS / WARNA BARIS (SESUAI CETAK ESDM) ================= -->
        <div class="bg-white px-4 py-3 rounded-2xl border border-slate-200/80 shadow-sm flex flex-wrap items-center gap-x-6 gap-y-2 text-xs text-slate-700">
            <span class="font-bold text-slate-800 text-[11px] uppercase tracking-wider flex items-center gap-1.5 mr-1">
                <i class="pi pi-info-circle text-[#005BAC]" />
                <span>Keterangan Status:</span>
            </span>
            <div class="flex items-center gap-2">
                <span class="w-4 h-4 rounded border border-rose-300 bg-[#ffc7c7] inline-block shrink-0 shadow-2xs"></span>
                <span class="font-medium text-slate-700">Sabtu/Minggu (Libur Mingguan)</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="w-4 h-4 rounded border border-amber-300 bg-[#ffe2a0] inline-block shrink-0 shadow-2xs"></span>
                <span class="font-medium text-slate-700">Libur Nasional</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="w-4 h-4 rounded border border-emerald-300 bg-[#c7f9cc] inline-block shrink-0 shadow-2xs"></span>
                <span class="font-medium text-slate-700">Lembur/Masuk di Hari Libur</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="w-4 h-4 rounded border border-slate-400 bg-[#d1d5db] inline-block shrink-0 shadow-2xs"></span>
                <span class="font-medium text-slate-700">Tidak Hadir (Alpha)</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="w-4 h-4 rounded border border-slate-300 bg-white inline-block shrink-0 shadow-2xs"></span>
                <span class="font-medium text-slate-700">Belum Berjalan</span>
            </div>
        </div>

        <!-- ================= CARD DATATABLE STANDAR /DATATABLE ================= -->
        <Card class="shadow-sm border border-slate-200/80 rounded-2xl overflow-hidden">
            <!-- Header Toolbar -->
            <template #header>
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-6 pt-5 pb-4 border-b border-slate-200/80 bg-slate-50/50">
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <InputText
                            v-model="instantSearch"
                            placeholder="Cari hari, tanggal, shift, jam, status..."
                            class="w-full sm:w-64"
                        >
                            <template #prefix><i class="pi pi-search text-slate-400" /></template>
                        </InputText>

                        <Select
                            v-model="statusFilter"
                            :options="statusFilterOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Filter Status"
                            class="w-full sm:w-56 text-xs"
                        />
                    </div>

                    <div class="flex items-center gap-2">
                        <span v-if="selectedRows.length" class="text-xs text-slate-600 font-medium">
                            {{ selectedRows.length }} baris terpilih
                        </span>
                        <Button
                            label="Export PDF"
                            icon="pi pi-file-pdf"
                            severity="danger"
                            outlined
                            size="small"
                            @click="handleExportPdf"
                        />
                    </div>
                </div>
            </template>

            <!-- Datatable Content -->
            <template #content>
                <DataTable
                    v-model:selection="selectedRows"
                    :value="filteredAttendances"
                    dataKey="id"
                    paginator
                    :rows="15"
                    :rowsPerPageOptions="[15, 31, 50, 100]"
                    sortMode="multiple"
                    removableSort
                    :rowClass="getRowClass"
                    tableStyle="min-width: 65rem"
                >
                    <Column selectionMode="multiple" headerStyle="width: 3rem" />
                    
                    <!-- Kolom No -->
                    <Column field="id" header="No" sortable style="width: 3.5rem" class="text-center">
                        <template #body="{ index }">
                            <span class="font-mono text-slate-400 text-xs">{{ index + 1 }}</span>
                        </template>
                    </Column>

                    <!-- Hari & Tanggal -->
                    <Column field="date" header="Hari &amp; Tanggal" sortable style="width: 10.5rem">
                        <template #body="{ data }">
                            <div>
                                <span class="font-bold text-slate-800 text-xs">{{ data.day_name }}</span>
                                <span class="text-slate-500 font-mono text-[11px] block">{{ data.formatted_date }}</span>
                            </div>
                        </template>
                    </Column>

                    <!-- Shift Kerja -->
                    <Column field="shift_name" header="Shift Kerja" sortable style="width: 8rem">
                        <template #body="{ data }">
                            <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 text-xs font-semibold">
                                {{ data.shift_name }}
                            </span>
                        </template>
                    </Column>

                    <!-- Jam Masuk -->
                    <Column field="check_in" header="Jam Masuk" sortable style="width: 7.5rem" class="text-center">
                        <template #body="{ data }">
                            <div class="font-mono text-xs">
                                <span :class="data.is_late ? 'text-amber-700 font-bold' : 'text-slate-800 font-semibold'">
                                    {{ data.check_in }}
                                </span>
                                <span v-if="data.is_late" class="block text-[10px] text-amber-600 font-sans font-semibold">
                                    (Terlambat)
                                </span>
                            </div>
                        </template>
                    </Column>

                    <!-- Jam Pulang -->
                    <Column field="check_out" header="Jam Pulang" sortable style="width: 7.5rem" class="text-center">
                        <template #body="{ data }">
                            <div class="font-mono text-xs">
                                <span :class="data.is_early_leave ? 'text-rose-700 font-bold' : 'text-slate-800 font-semibold'">
                                    {{ data.check_out }}
                                </span>
                                <span v-if="data.is_early_leave" class="block text-[10px] text-rose-600 font-sans font-semibold">
                                    (P. Cepat)
                                </span>
                            </div>
                        </template>
                    </Column>

                    <!-- Lembur -->
                    <Column field="overtime_duration" header="Lembur" sortable style="width: 6.5rem" class="text-center">
                        <template #body="{ data }">
                            <span v-if="data.overtime_duration && data.overtime_duration !== '-'" class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 font-mono text-[11px] font-bold">
                                {{ data.overtime_duration }}
                            </span>
                            <span v-else class="text-slate-300 text-xs">-</span>
                        </template>
                    </Column>

                    <!-- Status -->
                    <Column field="status" header="Status" sortable style="width: 10rem">
                        <template #body="{ data }">
                            <Tag
                                :value="data.status"
                                :severity="data.severity || 'info'"
                                class="text-[11px] font-semibold whitespace-nowrap"
                            />
                        </template>
                    </Column>

                    <!-- Keterangan (Dibatasi Lebarnya agar Rapi & Tidak Terlalu Lebar) -->
                    <Column field="notes" header="Keterangan" style="width: 14rem; max-width: 14rem">
                        <template #body="{ data }">
                            <div class="truncate text-slate-700 text-xs" :title="data.notes">
                                {{ data.notes }}
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </template>
        </Card>

    </div>
</template>

<style scoped>
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
    color: #94a3b8 !important;
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
