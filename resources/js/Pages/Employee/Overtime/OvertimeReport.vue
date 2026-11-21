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
    records: {
        type: Array,
        default: () => [],
    },
    targetEmployee: {
        type: Object,
        default: () => ({
            nip: '-',
            name: '-',
            division: 'Plant Banjaran',
            sub_division: '-',
            position: '-',
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
    isAtasan: {
        type: Boolean,
        default: false,
    },
    filters: {
        type: Object,
        default: () => ({
            nip: '',
            start_date: '',
            end_date: '',
            mode: 'all',
        }),
    },
    tabCounts: {
        type: Object,
        default: () => ({
            all: 0,
            verified: 0,
            wait_verif: 0,
            wait_real: 0,
        }),
    },
    summary: {
        type: Object,
        default: () => ({
            total_planned_hours: 0,
            planned_count: 0,
            total_real_hours: 0,
            real_count: 0,
            total_after_hours: 0,
            after_gt4_count: 0,
            total_counted_hours: 0,
            verified_count: 0,
            total_overtime_amount: 0,
            has_salary_rate: false,
        }),
    },
    overtimeTypes: {
        type: Object,
        default: () => ({}),
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

function formatHours(val) {
    const num = Number(val ?? 0);
    if (Number.isNaN(num)) return '0.00';
    return num.toFixed(2);
}

function formatRupiah(val) {
    const num = Number(val ?? 0);
    if (Number.isNaN(num)) return 'Rp 0';
    return 'Rp ' + Math.round(num).toLocaleString('id-ID');
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function sanitizeCsvCell(value) {
    const raw = String(value ?? '').replace(/\r?\n|\r/g, ' ').trim();
    const safe = /^[=+\-@\t\r]/.test(raw) ? `'${raw}` : raw;
    if (/[",\n]/.test(safe)) {
        return `"${safe.replace(/"/g, '""')}"`;
    }
    return safe;
}

const defaultBounds = getCurrentMonthBounds();

const filterForm = reactive({
    nip: props.filters.nip || props.targetEmployee?.nip || '',
    start_date: props.filters.start_date || defaultBounds.startStr,
    end_date: props.filters.end_date || defaultBounds.endStr,
    mode: props.filters.mode || 'all',
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
        if (next.mode) {
            filterForm.mode = next.mode;
        }
    },
    { deep: true }
);

const isLoadingReport = ref(false);
let removeStartListener = null;
let removeFinishListener = null;

onMounted(() => {
    removeStartListener = router.on('start', (event) => {
        const visitUrl = event?.detail?.visit?.url?.pathname || '';
        if (visitUrl.includes('/lembur/laporan')) {
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

const statusTabs = computed(() => [
    { key: 'all', label: 'Semua', count: props.tabCounts?.all ?? 0 },
    { key: 'verified', label: 'Terverifikasi', count: props.tabCounts?.verified ?? 0 },
    { key: 'wait_verif', label: 'Menunggu Verifikasi', count: props.tabCounts?.wait_verif ?? 0 },
    { key: 'wait_real', label: 'Menunggu Realisasi', count: props.tabCounts?.wait_real ?? 0 },
]);

const overtimeTypeOptions = computed(() => {
    const opts = [{ value: null, label: '-- Semua Jenis Lembur --' }];
    const map = props.overtimeTypes || {};
    Object.keys(map).forEach((key) => {
        opts.push({ value: Number(key), label: map[key] });
    });
    return opts;
});

const instantSearch = ref('');
const typeFilter = ref(null);
const tableFirst = ref(0);
const tableRows = ref(15);
const responsivePage = ref(1);
const responsivePerPage = 10;

const filteredRecords = computed(() => {
    const list = props.records || [];
    const q = instantSearch.value.toLowerCase().trim();
    const selectedType = typeFilter.value;

    return list.filter((item) => {
        const matchType = selectedType === null || Number(item.overtime_type) === Number(selectedType);
        const matchSearch =
            !q ||
            (item.spl_code && item.spl_code.toLowerCase().includes(q)) ||
            (item.formatted_date && item.formatted_date.toLowerCase().includes(q)) ||
            (item.day_name && item.day_name.toLowerCase().includes(q)) ||
            (item.type_label && item.type_label.toLowerCase().includes(q)) ||
            (item.task_description && item.task_description.toLowerCase().includes(q)) ||
            (item.status_label && item.status_label.toLowerCase().includes(q));

        return matchType && matchSearch;
    });
});

watch([instantSearch, typeFilter, () => props.records], () => {
    tableFirst.value = 0;
    responsivePage.value = 1;
});

const responsiveTotalPages = computed(() => Math.max(1, Math.ceil(filteredRecords.value.length / responsivePerPage)));
const paginatedCardRecords = computed(() => {
    const start = (responsivePage.value - 1) * responsivePerPage;
    return filteredRecords.value.slice(start, start + responsivePerPage);
});

const activePeriodLabel = computed(() => {
    const startStr = props.filters?.start_date || filterForm.start_date;
    const endStr = props.filters?.end_date || filterForm.end_date;
    return `${formatDisplayDate(startStr)} s/d ${formatDisplayDate(endStr)}`;
});

function navigateReport(params) {
    isLoadingReport.value = true;
    router.get('/lembur/laporan', params, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            isLoadingReport.value = false;
        },
    });
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

    navigateReport({
        nip: filterForm.nip,
        start_date: filterForm.start_date,
        end_date: filterForm.end_date,
        mode: filterForm.mode,
    });
}

function setModeTab(modeKey) {
    if (filterForm.mode === modeKey) return;
    filterForm.mode = modeKey;
    navigateReport({
        nip: filterForm.nip,
        start_date: filterForm.start_date,
        end_date: filterForm.end_date,
        mode: modeKey,
    });
}

function handleTabKeydown(event, idx) {
    const tabs = statusTabs.value;
    let nextIdx = null;
    if (event.key === 'ArrowRight') nextIdx = (idx + 1) % tabs.length;
    else if (event.key === 'ArrowLeft') nextIdx = (idx - 1 + tabs.length) % tabs.length;
    else if (event.key === 'Home') nextIdx = 0;
    else if (event.key === 'End') nextIdx = tabs.length - 1;

    if (nextIdx !== null) {
        event.preventDefault();
        const targetTab = tabs[nextIdx];
        setModeTab(targetTab.key);
        const btn = document.getElementById(`overtime-tab-${targetTab.key}`);
        btn?.focus();
    }
}

function setQuickRange(rangeType) {
    const today = new Date();
    let startObj = new Date(today.getFullYear(), today.getMonth(), today.getDate());
    const endObj = new Date(today.getFullYear(), today.getMonth(), today.getDate());

    if (rangeType === '7d') {
        startObj.setDate(endObj.getDate() - 6);
    } else if (rangeType === 'month') {
        startObj = new Date(today.getFullYear(), today.getMonth(), 1);
    }

    selectedStartDate.value = startObj;
    selectedEndDate.value = endObj;
    filterForm.start_date = formatDateLocal(startObj);
    filterForm.end_date = formatDateLocal(endObj);
    applyFilter();
}

function resetFilter() {
    const bounds = getCurrentMonthBounds();
    filterForm.nip = props.targetEmployee?.nip || '';
    filterForm.start_date = bounds.startStr;
    filterForm.end_date = bounds.endStr;
    filterForm.mode = 'all';
    selectedStartDate.value = bounds.startObj;
    selectedEndDate.value = bounds.endObj;
    instantSearch.value = '';
    typeFilter.value = null;
    applyFilter();
}

function clearTableFilters() {
    instantSearch.value = '';
    typeFilter.value = null;
}

function refreshReport() {
    isLoadingReport.value = true;
    router.reload({
        only: ['records', 'summary', 'tabCounts', 'targetEmployee', 'filters'],
        onFinish: () => {
            isLoadingReport.value = false;
        },
    });
}

function formatDeltaText(delta) {
    if (delta === null || delta === undefined || delta === 0) return null;
    const prefix = delta > 0 ? '+' : '';
    return `${prefix}${Number(delta).toFixed(2)} Jam dari ajuan`;
}

function exportCsv() {
    const list = filteredRecords.value || [];
    if (list.length === 0) {
        toast.add({
            severity: 'warn',
            summary: 'Data Kosong',
            detail: 'Tidak ada data lembur pada filter aktif yang dapat diekspor ke CSV.',
            life: 4000,
        });
        return;
    }

    const header = [
        'NO',
        'NPP',
        'NAMA',
        'TANGGAL LEMBUR',
        'NO SPL',
        'JAM DIAJUKAN',
        'REALISASI LEMBUR',
        'STATUS',
        'JAM IN (L)',
        'JAM OUT (L)',
        'JENIS LEMBUR',
        'TUGAS LEMBUR',
        'JAM HITUNG',
        'NOMINAL LEMBUR',
    ];

    const rows = [header.map(sanitizeCsvCell)];
    const empNip = props.targetEmployee?.nip || '-';
    const empName = props.targetEmployee?.name || '-';

    list.forEach((item, idx) => {
        const ajuanText = item.planned_hours > 0
            ? `${formatHours(item.planned_hours)} Jam (${item.planned_start} - ${item.planned_end})`
            : 'N/A';
        const realText = item.real_hours > 0
            ? `${formatHours(item.real_hours)} Jam (${item.real_date_start_short} ${item.effective_in} - ${item.real_date_end_short} ${item.effective_out})`
            : 'N/A';
        const countedText = item.is_verified ? `${formatHours(item.counted_hours)} Jam` : 'Belum Final';
        const nominalText = item.is_verified
            ? (props.summary.has_salary_rate ? formatRupiah(item.overtime_amount) : 'Menunggu Tarif Gaji')
            : 'Belum Final';

        rows.push([
            idx + 1,
            empNip,
            empName,
            item.formatted_date,
            item.spl_code,
            ajuanText,
            realText,
            item.status_label,
            item.jam_in2,
            item.jam_out2,
            item.type_label,
            item.task_description,
            countedText,
            nominalText,
        ].map(sanitizeCsvCell));
    });

    const csvContent = '\uFEFF' + rows.map((r) => r.join(',')).join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `laporan_lembur_${empNip}_${filterForm.start_date}_${filterForm.end_date}.csv`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
}

function handleExportPdf() {
    const list = filteredRecords.value || [];
    if (list.length === 0) {
        toast.add({
            severity: 'warn',
            summary: 'Data Kosong',
            detail: 'Tidak ada data lembur pada filter aktif yang dapat diekspor ke PDF.',
            life: 4000,
        });
        return;
    }

    const activeTabObj = statusTabs.value.find((t) => t.key === filterForm.mode);
    const activeTabLabel = activeTabObj ? `${activeTabObj.label} (${list.length} data)` : 'Semua';
    const nominalSummary = props.summary.has_salary_rate
        ? formatRupiah(props.summary.total_overtime_amount)
        : 'Menunggu Master Tarif Gaji';

    exportTableToPdf({
        title: 'LAPORAN LENGKAP LEMBUR PEGAWAI',
        subtitle: 'Sistem Elektronik Sumber Daya Manusia (eSDM) - Plant Banjaran',
        documentNo: 'KFPB-SDM-LAPLMB-01',
        paperSize: 'A4',
        orientation: 'landscape',
        metaInfo: [
            { label: 'Nama Pegawai', value: escapeHtml(`${props.targetEmployee?.name || '-'} (${props.targetEmployee?.nip || '-'})`) },
            { label: 'Unit / Jabatan', value: escapeHtml(`${props.targetEmployee?.division || '-'} / ${props.targetEmployee?.position || '-'}`) },
            { label: 'Periode', value: escapeHtml(`${formatDisplayDate(filterForm.start_date)} s/d ${formatDisplayDate(filterForm.end_date)}`) },
            { label: 'Tab Status', value: escapeHtml(activeTabLabel) },
            {
                label: 'Ringkasan Jam',
                value: escapeHtml(`Ajuan: ${formatHours(props.summary.total_planned_hours)} Jam | Realisasi: ${formatHours(props.summary.total_real_hours)} Jam | After Break: ${formatHours(props.summary.total_after_hours)} Jam | Jam Hitung: ${formatHours(props.summary.total_counted_hours)} Jam`),
            },
            { label: 'Total Nominal', value: escapeHtml(nominalSummary) },
        ],
        columns: [
            { header: 'No', width: '24px', align: 'center', render: (row, idx) => idx + 1 },
            {
                header: 'Tanggal & SPL',
                width: '86px',
                render: (row) => `<strong>${escapeHtml(row.formatted_date)}</strong><br><span style="color:#475569;font-size:8px;">${escapeHtml(row.day_name)} / ${escapeHtml(row.spl_code)}</span>`,
            },
            { header: 'Jenis Lembur', width: '85px', render: (row) => escapeHtml(row.type_label) },
            {
                header: 'Jam Diajukan',
                width: '72px',
                render: (row) => row.planned_hours > 0
                    ? `<strong>${formatHours(row.planned_hours)} Jam</strong><br><span style="color:#475569;font-size:8px;">${escapeHtml(row.planned_start)} - ${escapeHtml(row.planned_end)}</span>`
                    : 'N/A',
            },
            {
                header: 'Realisasi',
                width: '88px',
                render: (row) => row.real_hours > 0
                    ? `<strong>${formatHours(row.real_hours)} Jam</strong><br><span style="color:#475569;font-size:8px;">${escapeHtml(row.real_date_start_short)} ${escapeHtml(row.effective_in)} - ${escapeHtml(row.real_date_end_short)} ${escapeHtml(row.effective_out)}</span>`
                    : 'N/A',
            },
            { header: 'In / Out (L)', width: '62px', align: 'center', render: (row) => `${escapeHtml(row.jam_in2)} / ${escapeHtml(row.jam_out2)}` },
            { header: 'Status', width: '76px', render: (row) => `<strong>${escapeHtml(row.status_label)}</strong>` },
            { header: 'Tugas Lembur', render: (row) => escapeHtml(row.task_description) },
            {
                header: 'Jam Hitung',
                width: '58px',
                align: 'center',
                render: (row) => row.is_verified
                    ? `<strong style="color:#005BAC;">${formatHours(row.counted_hours)} Jam</strong>`
                    : '<span style="color:#475569;">Belum Final</span>',
            },
            {
                header: 'Nominal',
                width: '76px',
                align: 'right',
                render: (row) => {
                    if (!row.is_verified) return '<span style="color:#475569;">Belum Final</span>';
                    return props.summary.has_salary_rate
                        ? `<strong>${escapeHtml(formatRupiah(row.overtime_amount))}</strong>`
                        : '<span style="color:#475569;">Menunggu Tarif</span>';
                },
            },
        ],
        data: list,
    });
}
</script>

<template>
    <Head title="Laporan Lengkap Lembur - eSDM KFPB" />

    <div class="space-y-5 max-w-7xl mx-auto px-3 sm:px-6 pt-1 pb-6">
        <Toast position="top-right" />

        <!-- ================= TOP PAGE HEADER ================= -->
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
            <div class="space-y-1 min-w-0">
                <nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-2 text-xs font-medium text-slate-600">
                    <Link href="/absensi" class="hover:text-[#005BAC] flex items-center gap-1 py-0.5">
                        <i class="pi pi-calendar-check text-[11px]" aria-hidden="true" />
                        <span>Presensi</span>
                    </Link>
                    <span aria-hidden="true">/</span>
                    <Link href="/lembur/ajukan" class="hover:text-[#005BAC] py-0.5">
                        <span>Lembur</span>
                    </Link>
                    <span aria-hidden="true">/</span>
                    <span class="text-slate-900 font-semibold">Laporan Lengkap Lembur</span>
                </nav>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight flex flex-wrap items-center gap-2">
                    <span>Laporan Lengkap Lembur</span>
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
                    Rekapitulasi jam diajukan, realisasi jam lembur, jam hitung terverifikasi, dan nominal sesuai standar eSDM KFPB.
                </p>
            </div>

            <Link
                href="/lembur/ajukan"
                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 min-h-[44px] rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 font-semibold text-xs sm:text-sm transition-colors shrink-0 self-start sm:self-auto"
            >
                <i class="pi pi-arrow-left text-xs" aria-hidden="true" />
                <span>Pengajuan &amp; Riwayat SPL</span>
            </Link>
        </div>

        <!-- ================= 4 KARTU RINGKASAN PERHITUNGAN LEMBUR ================= -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
            <!-- 1. Total Jam Diajukan -->
            <div class="bg-white p-4 rounded-xl border border-slate-200/90 shadow-xs flex items-start justify-between gap-3">
                <div class="min-w-0 space-y-1">
                    <span class="text-[11px] font-bold text-slate-600 uppercase tracking-wider block">
                        Total Jam Diajukan
                    </span>
                    <span class="text-xl font-bold font-mono text-slate-900 block">
                        {{ formatHours(summary.total_planned_hours) }} Jam
                    </span>
                    <span class="text-xs text-slate-600 block">
                        Dari {{ summary.planned_count }} pengajuan SPL periode ini
                    </span>
                </div>
                <div class="size-9 rounded-lg bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-700 shrink-0">
                    <i class="pi pi-file-edit text-sm" aria-hidden="true" />
                </div>
            </div>

            <!-- 2. Total Jam Realisasi -->
            <div class="bg-white p-4 rounded-xl border border-slate-200/90 shadow-xs flex items-start justify-between gap-3">
                <div class="min-w-0 space-y-1">
                    <span class="text-[11px] font-bold text-[#005BAC] uppercase tracking-wider block">
                        Total Jam Realisasi
                    </span>
                    <span class="text-xl font-bold font-mono text-[#004280] block">
                        {{ formatHours(summary.total_real_hours) }} Jam
                    </span>
                    <span class="text-xs text-slate-600 block">
                        Dari {{ summary.real_count }} data pada tab aktif
                    </span>
                </div>
                <div class="size-9 rounded-lg bg-blue-50 border border-blue-200 flex items-center justify-center text-[#005BAC] shrink-0">
                    <i class="pi pi-clock text-sm" aria-hidden="true" />
                </div>
            </div>

            <!-- 3. Total Jam Real (After Break) -->
            <div class="bg-white p-4 rounded-xl border border-slate-200/90 shadow-xs flex items-start justify-between gap-3">
                <div class="min-w-0 space-y-1">
                    <span class="text-[11px] font-bold text-amber-800 uppercase tracking-wider block">
                        Total Jam Real (After)
                    </span>
                    <span class="text-xl font-bold font-mono text-amber-800 block">
                        {{ formatHours(summary.total_after_hours) }} Jam
                    </span>
                    <span class="text-xs text-slate-600 block">
                        {{ summary.after_gt4_count }} data &gt; 4 jam dipotong 1 jam istirahat
                    </span>
                </div>
                <div class="size-9 rounded-lg bg-amber-50 border border-amber-200 flex items-center justify-center text-amber-800 shrink-0">
                    <i class="pi pi-hourglass text-sm" aria-hidden="true" />
                </div>
            </div>

            <!-- 4. Jam Hitung & Nominal (Focal KPI) -->
            <div class="bg-white p-4 rounded-xl border border-slate-200 border-t-4 border-t-[#005BAC] shadow-xs flex items-start justify-between gap-3">
                <div class="min-w-0 space-y-1">
                    <span class="text-[11px] font-bold text-emerald-800 uppercase tracking-wider block">
                        Jam Hitung &amp; Nominal
                    </span>
                    <span class="text-xl font-bold font-mono text-emerald-800 block">
                        {{ formatHours(summary.total_counted_hours) }} Jam
                    </span>
                    <span v-if="summary.has_salary_rate" class="text-xs font-bold font-mono text-emerald-800 block">
                        {{ formatRupiah(summary.total_overtime_amount) }} ({{ summary.verified_count }} terverifikasi)
                    </span>
                    <span v-else class="text-xs text-slate-600 block">
                        {{ summary.verified_count }} terverifikasi / Nominal Rp menunggu Master Tarif Gaji
                    </span>
                </div>
                <div class="size-9 rounded-lg bg-emerald-50 border border-emerald-200 flex items-center justify-center text-emerald-800 shrink-0">
                    <i class="pi pi-check-circle text-sm" aria-hidden="true" />
                </div>
            </div>
        </div>

        <!-- ================= PANEL FILTER PERIODE & PEGAWAI ================= -->
        <section
            aria-label="Filter Periode dan Pegawai"
            class="bg-white rounded-2xl border border-slate-200 border-t-4 border-t-[#005BAC] shadow-sm overflow-hidden"
        >
            <div class="px-5 sm:px-6 py-4 border-b border-slate-200/80 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="size-9 rounded-xl bg-blue-50 text-[#005BAC] flex items-center justify-center shrink-0 border border-blue-200/60">
                        <i class="pi pi-sliders-h text-base" aria-hidden="true" />
                    </div>
                    <div class="min-w-0">
                        <h2 class="font-bold text-sm sm:text-base text-slate-900 tracking-tight">
                            Filter Periode &amp; Pegawai
                        </h2>
                        <p class="text-xs text-slate-600">
                            Pilih pegawai, rentang tanggal pelaksanaan lembur, atau rentang cepat.
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        :disabled="isLoadingReport"
                        class="px-3 py-2 min-h-[44px] rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold transition-colors cursor-pointer disabled:opacity-50"
                        @click="setQuickRange('today')"
                    >
                        Hari Ini
                    </button>
                    <button
                        type="button"
                        :disabled="isLoadingReport"
                        class="px-3 py-2 min-h-[44px] rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold transition-colors cursor-pointer disabled:opacity-50"
                        @click="setQuickRange('7d')"
                    >
                        7 Hari
                    </button>
                    <button
                        type="button"
                        :disabled="isLoadingReport"
                        class="px-3 py-2 min-h-[44px] rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold transition-colors cursor-pointer disabled:opacity-50"
                        @click="setQuickRange('month')"
                    >
                        Bulan Ini
                    </button>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-2 min-h-[44px] rounded-lg bg-slate-100 text-slate-700 text-xs font-mono font-semibold border border-slate-200">
                        <i class="pi pi-calendar text-[11px] text-[#005BAC]" aria-hidden="true" />
                        <span>{{ activePeriodLabel }}</span>
                    </span>
                </div>
            </div>

            <form @submit.prevent="applyFilter" class="p-5 sm:p-6 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                    <!-- Pegawai -->
                    <div class="min-w-0 sm:col-span-2 lg:col-span-2">
                        <span id="overtime-filter-nip-label" class="block text-xs font-bold text-slate-700 mb-1.5">
                            Nama Pegawai
                        </span>
                        <Select
                            v-if="employeeOptions.length > 1"
                            v-model="filterForm.nip"
                            :options="employeeOptions"
                            optionLabel="name"
                            optionValue="nip"
                            aria-labelledby="overtime-filter-nip-label"
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
                        <label for="overtime-filter-start-date" class="block text-xs font-bold text-slate-700 mb-1.5">
                            Tanggal Awal
                        </label>
                        <DatePicker
                            v-model="selectedStartDate"
                            inputId="overtime-filter-start-date"
                            dateFormat="dd/mm/yy"
                            placeholder="dd/mm/yyyy"
                            showIcon
                            fluid
                            :manualInput="false"
                            :pt="{
                                pcInputText: { root: { name: 'start_date', autocomplete: 'off' } },
                                dropdown: { 'aria-label': 'Buka kalender tanggal awal lembur' },
                                panel: { 'aria-label': 'Pilih tanggal awal periode lembur' },
                            }"
                        />
                    </div>

                    <!-- Tanggal Akhir -->
                    <div class="min-w-0">
                        <label for="overtime-filter-end-date" class="block text-xs font-bold text-slate-700 mb-1.5">
                            Tanggal Akhir
                        </label>
                        <DatePicker
                            v-model="selectedEndDate"
                            inputId="overtime-filter-end-date"
                            dateFormat="dd/mm/yy"
                            placeholder="dd/mm/yyyy"
                            showIcon
                            fluid
                            :manualInput="false"
                            :pt="{
                                pcInputText: { root: { name: 'end_date', autocomplete: 'off' } },
                                dropdown: { 'aria-label': 'Buka kalender tanggal akhir lembur' },
                                panel: { 'aria-label': 'Pilih tanggal akhir periode lembur' },
                            }"
                        />
                    </div>
                </div>

                <!-- Baris Aksi & Identitas Pegawai -->
                <div class="pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-2 text-xs text-slate-600 min-w-0">
                        <span>
                            Pegawai:
                            <span class="font-bold text-slate-900">{{ targetEmployee.name }}</span>
                            <span class="font-mono text-slate-700">({{ targetEmployee.nip }})</span>
                            <span v-if="targetEmployee.division"> &bull; {{ targetEmployee.division }}</span>
                            <span v-if="targetEmployee.position"> &bull; {{ targetEmployee.position }}</span>
                        </span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded bg-slate-100 border border-slate-200 text-slate-700 font-semibold">
                            {{ isAtasan ? 'Skema Hitung: Atasan (Jam Real After)' : 'Skema Hitung: Pelaksana (Indeks 1.5x / 2x / 3x / 4x)' }}
                        </span>
                    </div>

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
                            label="Terapkan Filter"
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

        <!-- ================= CARD TABEL RINCIAN LEMBUR ================= -->
        <section
            aria-label="Rincian Pelaksanaan dan Perhitungan Lembur"
            class="bg-white rounded-2xl border border-slate-200 border-t-4 border-t-[#005BAC] shadow-sm overflow-hidden"
        >
            <!-- Card Header -->
            <div class="px-5 sm:px-6 py-4 border-b border-slate-200/80 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="size-9 rounded-xl bg-blue-50 text-[#005BAC] flex items-center justify-center shrink-0 border border-blue-200/60">
                        <i class="pi pi-list-check text-base" aria-hidden="true" />
                    </div>
                    <div class="min-w-0">
                        <h2 class="font-bold text-sm sm:text-base text-slate-900 tracking-tight">
                            Rincian Pelaksanaan &amp; Perhitungan Lembur
                        </h2>
                        <p class="text-xs text-slate-600">
                            Menampilkan {{ filteredRecords.length }} dari {{ records.length }} data pada tab aktif periode {{ activePeriodLabel }}.
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
                        label="Export CSV"
                        icon="pi pi-file-excel"
                        severity="success"
                        outlined
                        size="small"
                        class="min-h-[44px] text-xs !text-emerald-800 !border-emerald-300 hover:!bg-emerald-50"
                        @click="exportCsv"
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

            <!-- Tab Status Lembur (Sesuai eSDM Native adminlembur_detail) -->
            <div
                role="tablist"
                aria-label="Filter status verifikasi lembur"
                class="px-5 sm:px-6 py-3 border-b border-slate-200/80 bg-slate-50/70 flex items-center gap-2 overflow-x-auto"
            >
                <button
                    v-for="(tab, idx) in statusTabs"
                    :id="`overtime-tab-${tab.key}`"
                    :key="tab.key"
                    type="button"
                    role="tab"
                    :aria-selected="String(filterForm.mode === tab.key)"
                    :tabindex="filterForm.mode === tab.key ? 0 : -1"
                    :class="[
                        'inline-flex items-center gap-2 px-3.5 py-2 min-h-[44px] rounded-xl text-xs font-semibold whitespace-nowrap border transition-colors cursor-pointer shrink-0',
                        filterForm.mode === tab.key
                            ? 'bg-[#005BAC] text-white border-[#005BAC]'
                            : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100',
                    ]"
                    @click="setModeTab(tab.key)"
                    @keydown="handleTabKeydown($event, idx)"
                >
                    <span>{{ tab.label }}</span>
                    <span
                        :class="[
                            'px-1.5 py-0.5 rounded-md font-mono text-[11px] font-bold',
                            filterForm.mode === tab.key
                                ? 'bg-white/20 text-white'
                                : 'bg-slate-100 text-slate-700',
                        ]"
                    >
                        {{ tab.count }}
                    </span>
                </button>
            </div>

            <!-- Bar Filter Pencarian Client-Side -->
            <div v-if="records.length > 0" class="px-5 sm:px-6 pt-4 pb-1 grid grid-cols-1 sm:grid-cols-12 gap-3">
                <div class="sm:col-span-7">
                    <label for="overtime-instant-search" class="sr-only">
                        Cari kode SPL, tanggal, jenis lembur, status, atau tugas lembur
                    </label>
                    <IconField class="w-full">
                        <InputIcon class="pi pi-search text-slate-600" aria-hidden="true" />
                        <InputText
                            id="overtime-instant-search"
                            v-model="instantSearch"
                            name="overtime_instant_search"
                            placeholder="Cari tanggal, kode SPL, jenis lembur, tugas, status..."
                            class="w-full min-h-[44px] text-sm"
                        />
                    </IconField>
                </div>
                <div class="sm:col-span-5">
                    <span id="overtime-type-filter-label" class="sr-only">Filter jenis lembur</span>
                    <Select
                        v-model="typeFilter"
                        :options="overtimeTypeOptions"
                        optionLabel="label"
                        optionValue="value"
                        aria-labelledby="overtime-type-filter-label"
                        placeholder="-- Semua Jenis Lembur --"
                        class="w-full"
                    />
                </div>
            </div>

            <!-- Isi Card -->
            <div class="p-5 sm:p-6">
                <!-- Loading State -->
                <div
                    v-if="isLoadingReport"
                    role="status"
                    aria-live="polite"
                    class="py-10 px-4 text-center space-y-3"
                >
                    <i class="pi pi-spin motion-reduce:animate-none pi-spinner text-2xl text-[#005BAC]" aria-hidden="true" />
                    <p class="text-sm font-medium text-slate-700">Memuat data rekapitulasi lembur...</p>
                </div>

                <!-- Empty State Data Periode / Tab Kosong -->
                <div v-else-if="records.length === 0" class="text-center py-10 px-4 space-y-3">
                    <div class="size-12 rounded-2xl bg-slate-100 text-slate-600 flex items-center justify-center mx-auto">
                        <i class="pi pi-inbox text-xl" aria-hidden="true" />
                    </div>
                    <div class="space-y-1 max-w-md mx-auto">
                        <p class="text-sm font-bold text-slate-900">Belum Ada Data Lembur pada Filter Ini</p>
                        <p class="text-xs text-slate-600">
                            Coba ubah tab status, pilih rentang tanggal lain, atau kembalikan ke periode bulan berjalan.
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
                <div v-else-if="filteredRecords.length === 0" class="text-center py-10 px-4 space-y-3">
                    <div class="size-12 rounded-2xl bg-slate-100 text-slate-600 flex items-center justify-center mx-auto">
                        <i class="pi pi-search text-xl" aria-hidden="true" />
                    </div>
                    <div class="space-y-1 max-w-md mx-auto">
                        <p class="text-sm font-bold text-slate-900">Tidak Ada Data yang Cocok dengan Pencarian</p>
                        <p class="text-xs text-slate-600">
                            Ubah kata kunci pencarian atau pilih semua jenis lembur untuk menampilkan kembali seluruh baris.
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
                            :value="filteredRecords"
                            dataKey="id"
                            paginator
                            :rowsPerPageOptions="[15, 25, 50, 100]"
                            sortMode="multiple"
                            removableSort
                            responsiveLayout="scroll"
                            class="text-sm"
                            :pt="{
                                bodyCell: { class: '!py-2.5 !align-top' },
                            }"
                        >
                            <Column header="No" style="width: 3rem" class="text-center">
                                <template #body="{ index }">
                                    <span class="font-mono text-slate-600 text-xs">{{ tableFirst + index + 1 }}</span>
                                </template>
                            </Column>

                            <Column field="date" header="Tanggal &amp; SPL" sortable style="min-width: 9rem">
                                <template #body="{ data }">
                                    <div class="space-y-0.5">
                                        <span class="font-bold text-slate-900 text-xs block">
                                            {{ data.day_name }}, {{ data.formatted_date }}
                                        </span>
                                        <span class="font-mono text-[11px] font-semibold text-[#005BAC] block">
                                            {{ data.spl_code }}
                                        </span>
                                        <span v-if="data.request_date !== '-'" class="text-[11px] text-slate-600 block">
                                            Ajuan: {{ data.request_date }}
                                        </span>
                                    </div>
                                </template>
                            </Column>

                            <Column field="type_label" header="Jenis Lembur" sortable style="min-width: 8.5rem">
                                <template #body="{ data }">
                                    <span class="px-2 py-0.5 rounded-md bg-slate-100 border border-slate-200/80 text-slate-800 text-xs font-semibold inline-block">
                                        {{ data.type_label }}
                                    </span>
                                </template>
                            </Column>

                            <Column field="planned_hours" header="Jam Diajukan" sortable style="min-width: 7.5rem">
                                <template #body="{ data }">
                                    <div v-if="data.planned_hours > 0" class="space-y-0.5">
                                        <span class="font-mono font-bold text-slate-900 text-xs block">
                                            {{ formatHours(data.planned_hours) }} Jam
                                        </span>
                                        <span class="font-mono text-[11px] text-slate-600 block">
                                            {{ data.planned_start }} - {{ data.planned_end }}
                                        </span>
                                    </div>
                                    <span v-else class="text-xs text-slate-600">N/A</span>
                                </template>
                            </Column>

                            <Column field="real_hours" header="Realisasi Lembur" sortable style="min-width: 9.5rem">
                                <template #body="{ data }">
                                    <div v-if="data.real_hours > 0" class="space-y-0.5">
                                        <span class="font-mono font-bold text-slate-900 text-xs block">
                                            {{ formatHours(data.real_hours) }} Jam
                                        </span>
                                        <span class="font-mono text-[11px] text-slate-600 block">
                                            {{ data.real_date_start_short }} {{ data.effective_in }} - {{ data.real_date_end_short }} {{ data.effective_out }}
                                        </span>
                                        <span
                                            v-if="formatDeltaText(data.delta_hours)"
                                            :class="[
                                                'font-mono text-[11px] font-semibold block',
                                                data.delta_hours >= 0 ? 'text-emerald-800' : 'text-rose-700',
                                            ]"
                                        >
                                            {{ formatDeltaText(data.delta_hours) }}
                                        </span>
                                    </div>
                                    <span v-else class="text-xs text-slate-600">N/A</span>
                                </template>
                            </Column>

                            <Column header="In / Out (L)" style="min-width: 6.5rem" class="text-center">
                                <template #body="{ data }">
                                    <div class="font-mono text-xs text-slate-800 space-y-0.5">
                                        <span class="block">{{ data.jam_in2 }}</span>
                                        <span class="block text-slate-600">{{ data.jam_out2 }}</span>
                                    </div>
                                </template>
                            </Column>

                            <Column field="status_label" header="Status" sortable style="min-width: 8.5rem">
                                <template #body="{ data }">
                                    <Tag
                                        :value="data.status_label"
                                        :severity="data.status_severity || 'info'"
                                        class="text-xs font-semibold whitespace-nowrap"
                                    />
                                </template>
                            </Column>

                            <Column field="task_description" header="Tugas Lembur" style="min-width: 11rem; max-width: 15rem">
                                <template #body="{ data }">
                                    <p class="text-xs text-slate-700 line-clamp-2" :title="data.task_description">
                                        {{ data.task_description }}
                                    </p>
                                </template>
                            </Column>

                            <Column field="counted_hours" header="Jam Hitung" sortable style="min-width: 6.5rem" class="text-right">
                                <template #body="{ data }">
                                    <div v-if="data.is_verified" class="space-y-0.5">
                                        <span class="font-mono font-bold text-[#005BAC] text-xs block">
                                            {{ formatHours(data.counted_hours) }} Jam
                                        </span>
                                        <span class="text-[11px] text-slate-600 block">
                                            After: {{ formatHours(data.after_break_hours) }} Jam
                                        </span>
                                    </div>
                                    <span v-else class="text-xs italic text-slate-600">Belum Final</span>
                                </template>
                            </Column>

                            <Column field="overtime_amount" header="Nominal" sortable style="min-width: 7.5rem" class="text-right">
                                <template #body="{ data }">
                                    <template v-if="data.is_verified">
                                        <span
                                            v-if="summary.has_salary_rate"
                                            class="font-mono font-bold text-emerald-800 text-xs whitespace-nowrap"
                                        >
                                            {{ formatRupiah(data.overtime_amount) }}
                                        </span>
                                        <span v-else class="text-[11px] text-slate-600 whitespace-nowrap">
                                            Menunggu Tarif
                                        </span>
                                    </template>
                                    <span v-else class="text-xs italic text-slate-600">Belum Final</span>
                                </template>
                            </Column>
                        </DataTable>
                    </div>

                    <!-- Mobile & Tablet Responsive Cards (< lg: < 1024px) -->
                    <div class="lg:hidden space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <article
                                v-for="(item, idx) in paginatedCardRecords"
                                :key="item.id"
                                class="p-4 rounded-xl border border-slate-200 bg-white flex flex-col justify-between gap-3"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <h3 class="font-bold text-sm text-slate-900 leading-snug">
                                            {{ item.day_name }}, {{ item.formatted_date }}
                                        </h3>
                                        <p class="text-xs font-semibold text-slate-600 mt-0.5">
                                            <span class="font-mono">#{{ (responsivePage - 1) * responsivePerPage + idx + 1 }}</span>
                                            <span aria-hidden="true"> &bull; </span>
                                            <span class="font-mono text-[#005BAC]">{{ item.spl_code }}</span>
                                            <span aria-hidden="true"> &bull; </span>
                                            <span>{{ item.type_label }}</span>
                                        </p>
                                    </div>
                                    <Tag
                                        :value="item.status_label"
                                        :severity="item.status_severity || 'info'"
                                        class="text-xs font-semibold shrink-0"
                                    />
                                </div>

                                <div class="grid grid-cols-3 gap-2 py-2.5 border-y border-slate-200 text-xs">
                                    <div class="min-w-0">
                                        <span class="text-[11px] font-bold uppercase text-slate-600 block">Diajukan</span>
                                        <span class="font-mono font-bold text-slate-900 block">
                                            {{ item.planned_hours > 0 ? `${formatHours(item.planned_hours)} Jam` : 'N/A' }}
                                        </span>
                                        <span v-if="item.planned_hours > 0" class="font-mono text-[11px] text-slate-600 block">
                                            {{ item.planned_start }}-{{ item.planned_end }}
                                        </span>
                                    </div>
                                    <div class="min-w-0">
                                        <span class="text-[11px] font-bold uppercase text-slate-600 block">Realisasi</span>
                                        <span class="font-mono font-bold text-slate-900 block">
                                            {{ item.real_hours > 0 ? `${formatHours(item.real_hours)} Jam` : 'N/A' }}
                                        </span>
                                        <span v-if="item.real_hours > 0" class="font-mono text-[11px] text-slate-600 block">
                                            {{ item.effective_in }}-{{ item.effective_out }}
                                        </span>
                                    </div>
                                    <div class="min-w-0">
                                        <span class="text-[11px] font-bold uppercase text-slate-600 block">Jam Hitung</span>
                                        <span
                                            v-if="item.is_verified"
                                            class="font-mono font-bold text-[#005BAC] block"
                                        >
                                            {{ formatHours(item.counted_hours) }} Jam
                                        </span>
                                        <span v-else class="italic text-slate-600 block">Belum Final</span>
                                    </div>
                                </div>

                                <div class="space-y-1.5 text-xs">
                                    <p class="text-slate-700 leading-relaxed">
                                        <span class="font-semibold text-slate-800">Tugas:</span>
                                        {{ item.task_description || '-' }}
                                    </p>
                                    <div class="flex flex-wrap items-center justify-between gap-2 pt-1 text-slate-600">
                                        <span class="font-mono">
                                            In/Out (L): {{ item.jam_in2 }} / {{ item.jam_out2 }}
                                        </span>
                                        <span
                                            v-if="item.is_verified && summary.has_salary_rate"
                                            class="font-mono font-bold text-emerald-800"
                                        >
                                            {{ formatRupiah(item.overtime_amount) }}
                                        </span>
                                        <span v-else-if="item.is_verified" class="text-slate-600">
                                            Nominal: Menunggu Tarif
                                        </span>
                                    </div>
                                </div>
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

            <!-- Ringkasan Rumus Resmi KFPB -->
            <div class="px-5 sm:px-6 pb-5">
                <details class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-700">
                    <summary class="font-semibold text-slate-900 cursor-pointer py-1">
                        Ketentuan Rumus Perhitungan Lembur eSDM KFPB
                    </summary>
                    <ul class="mt-2.5 space-y-1.5 list-disc pl-5 text-slate-700 leading-relaxed">
                        <li>
                            <strong>Lembur Hari Kerja &amp; Flextime (Pelaksana):</strong> Kurang dari 1 jam tidak dihitung (0). Tepat 1 jam dikali 1,5. Lebih dari 1 jam: 1 jam pertama dikali 1,5 (90 menit) dan jam berikutnya dikali 2. Jika melebihi 4 jam (&gt; 239 menit pada Hari Kerja / &gt; 240 menit pada Flextime), dipotong 1 jam istirahat.
                        </li>
                        <li>
                            <strong>Lembur Sabtu/Minggu (Pelaksana):</strong> Kurang dari 4 jam dikali 2. Mulai 4 jam sampai 7 jam 59 menit dipotong 1 jam istirahat lalu dikali 2. Jam ke-8 dikali 3 dan jam ke-9 ke atas dikali 4.
                        </li>
                        <li>
                            <strong>Lembur Hari Besar/Raya (Pelaksana):</strong> Kurang dari 4 jam dikali 2. Rentang 4 jam sampai 7 jam 59 menit dipotong 1 jam istirahat lalu dikali 2; mulai 8 jam ke atas mengikuti skema jam ke-8 ke atas dikali 4 sesuai eSDM native.
                        </li>
                        <li>
                            <strong>Skema Atasan (Asman / Manager):</strong> Menggunakan total jam realisasi yang dipotong 1 jam istirahat apabila lebih dari 4 jam, tanpa pengali indeks lembur.
                        </li>
                    </ul>
                </details>
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
</style>
