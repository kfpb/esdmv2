<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { generateQrSvg } from '@/Utils/qrcode';

defineOptions({ layout: AuthenticatedLayout });

const props = defineProps({
    permits: {
        type: Object,
        required: true,
    },
    years: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({
            bulan: 'all',
            tahun: new Date().getFullYear().toString(),
            status: 'all',
            q: '',
            per_page: 25,
        }),
    },
    statistics: {
        type: Object,
        default: () => ({
            total: 0,
            pending: 0,
            approved: 0,
            rejected: 0,
        }),
    },
});

const toast = useToast();

// State Filter Form
const filterForm = reactive({
    bulan: props.filters.bulan || 'all',
    tahun: props.filters.tahun || new Date().getFullYear().toString(),
    status: props.filters.status !== undefined ? props.filters.status.toString() : 'all',
    q: props.filters.q || '',
    per_page: props.filters.per_page || 25,
});

const bulanList = [
    { value: 'all', label: '-- Semua Bulan --' },
    { value: '1', label: 'Januari' },
    { value: '2', label: 'Februari' },
    { value: '3', label: 'Maret' },
    { value: '4', label: 'April' },
    { value: '5', label: 'Mei' },
    { value: '6', label: 'Juni' },
    { value: '7', label: 'Juli' },
    { value: '8', label: 'Agustus' },
    { value: '9', label: 'September' },
    { value: '10', label: 'Oktober' },
    { value: '11', label: 'November' },
    { value: '12', label: 'Desember' },
];

function applyFilter() {
    router.get('/admin/izin-keluar', {
        bulan: filterForm.bulan,
        tahun: filterForm.tahun,
        status: filterForm.status,
        q: filterForm.q,
        per_page: filterForm.per_page,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
}

function resetFilter() {
    filterForm.bulan = 'all';
    filterForm.tahun = new Date().getFullYear().toString();
    filterForm.status = 'all';
    filterForm.q = '';
    filterForm.per_page = 25;
    applyFilter();
}

// Modal Konfirmasi Persetujuan / Penolakan
const confirmDialog = reactive({
    show: false,
    item: null,
    action: null, // 'approve' | 'reject'
    processing: false,
});

function openConfirmModal(item, action) {
    confirmDialog.item = item;
    confirmDialog.action = action;
    confirmDialog.processing = false;
    confirmDialog.show = true;
}

function closeConfirmModal() {
    confirmDialog.show = false;
    confirmDialog.item = null;
    confirmDialog.action = null;
    confirmDialog.processing = false;
}

function handleConfirmAction() {
    if (!confirmDialog.item || !confirmDialog.action) return;

    confirmDialog.processing = true;
    const statusCode = confirmDialog.action === 'approve' ? 1 : 2;

    router.post(`/admin/izin-keluar/${confirmDialog.item.id}/status`, {
        status: statusCode,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({
                severity: confirmDialog.action === 'approve' ? 'success' : 'warn',
                summary: confirmDialog.action === 'approve' ? 'Disetujui' : 'Ditolak',
                detail: confirmDialog.action === 'approve'
                    ? `Izin keluar untuk ${confirmDialog.item.nama_pegawai} berhasil disetujui.`
                    : `Izin keluar untuk ${confirmDialog.item.nama_pegawai} berhasil ditolak.`,
                life: 3500,
            });
            closeConfirmModal();
        },
        onError: () => {
            toast.add({
                severity: 'error',
                summary: 'Gagal Memproses',
                detail: 'Terjadi kesalahan sistem saat memperbarui status izin.',
                life: 3500,
            });
            confirmDialog.processing = false;
        },
        onFinish: () => {
            confirmDialog.processing = false;
        },
    });
}

// Modal Tiket Gate Pass Satpam
const showTicketModal = ref(false);
const activeTicket = ref(null);

const qrSvgHtml = computed(() => {
    if (!activeTicket.value) return '';
    const code = activeTicket.value.kode_izin || `IK-${activeTicket.value.id}`;
    return generateQrSvg(code, 180);
});

function openTicketModal(item) {
    activeTicket.value = item;
    showTicketModal.value = true;
}

function closeTicketModal() {
    showTicketModal.value = false;
    activeTicket.value = null;
}

// Tutup modal dengan tombol Escape
function handleEscapeKey(e) {
    if (e.key === 'Escape') {
        if (showTicketModal.value) closeTicketModal();
        if (confirmDialog.show) closeConfirmModal();
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleEscapeKey);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleEscapeKey);
});
</script>

<template>
    <Head title="Rekapitulasi Izin Keluar Kantor - Admin eSDM KFPB" />
    <Toast position="top-right" />

    <div class="space-y-5 max-w-7xl mx-auto px-3 sm:px-6 py-2">

        <!-- ================= TOP HEADER BREADCRUMB & TITLE ================= -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="space-y-1">
                <div class="flex items-center gap-2 text-xs font-medium text-slate-500">
                    <span class="text-slate-400">Pusat Verifikasi</span>
                    <span>/</span>
                    <span class="text-slate-800 font-semibold">Rekap Izin Keluar Kantor</span>
                </div>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
                    Pusat Verifikasi & Rekap Izin Keluar Kantor
                </h1>
                <p class="text-xs sm:text-sm text-slate-500">
                    Persetujuan permohonan izin keluar sementara maupun izin datang terlambat pegawai Kimia Farma Plant Banjaran.
                </p>
            </div>

            <div class="flex items-center gap-2 shrink-0 self-start sm:self-auto">
                <Link
                    href="/izin-keluar"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-700 font-semibold text-xs sm:text-sm transition-colors shadow-xs"
                >
                    <i class="pi pi-external-link text-xs" />
                    <span>Layanan Mandiri Pegawai</span>
                </Link>
            </div>
        </div>

        <!-- ================= 4 KARTU STATISTIK RINGKAS (TANPA GRAFIK LEBAY) ================= -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4">
            <!-- Total Permohonan -->
            <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Total Pengajuan</span>
                    <span class="text-xl sm:text-2xl font-bold font-mono text-slate-800">{{ statistics.total.toLocaleString('id-ID') }}</span>
                    <span class="text-[10px] text-slate-400 block mt-0.5">Sesuai filter aktif</span>
                </div>
                <div class="size-10 rounded-xl bg-slate-100 flex items-center justify-center text-slate-600">
                    <i class="pi pi-list text-base" />
                </div>
            </div>

            <!-- Menunggu Persetujuan -->
            <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold text-amber-600 uppercase tracking-wider block">Menunggu Approval</span>
                    <span class="text-xl sm:text-2xl font-bold font-mono text-slate-800">{{ statistics.pending.toLocaleString('id-ID') }}</span>
                    <span class="text-[10px] text-amber-500 font-medium block mt-0.5">Perlu Tindakan</span>
                </div>
                <div class="size-10 rounded-xl bg-amber-50 flex items-center justify-center text-amber-600 border border-amber-100">
                    <i class="pi pi-clock text-base" />
                </div>
            </div>

            <!-- Disetujui -->
            <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold text-emerald-600 uppercase tracking-wider block">Disetujui</span>
                    <span class="text-xl sm:text-2xl font-bold font-mono text-slate-800">{{ statistics.approved.toLocaleString('id-ID') }}</span>
                    <span class="text-[10px] text-slate-400 block mt-0.5">Gate Pass Aktif</span>
                </div>
                <div class="size-10 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600 border border-emerald-100">
                    <i class="pi pi-check-circle text-base" />
                </div>
            </div>

            <!-- Ditolak -->
            <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold text-rose-600 uppercase tracking-wider block">Ditolak</span>
                    <span class="text-xl sm:text-2xl font-bold font-mono text-slate-800">{{ statistics.rejected.toLocaleString('id-ID') }}</span>
                    <span class="text-[10px] text-slate-400 block mt-0.5">Tidak Diizinkan</span>
                </div>
                <div class="size-10 rounded-xl bg-rose-50 flex items-center justify-center text-rose-600 border border-rose-100">
                    <i class="pi pi-times-circle text-base" />
                </div>
            </div>
        </div>

        <!-- ================= PANEL FILTER PENCARIAN ================= -->
        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-sm">
            <form @submit.prevent="applyFilter" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3 items-end">
                <!-- Bulan -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Bulan</label>
                    <select
                        v-model="filterForm.bulan"
                        class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs text-slate-800 bg-white focus:outline-none focus:ring-2 focus:ring-[#005BAC]/30 focus:border-[#005BAC]"
                    >
                        <option v-for="b in bulanList" :key="b.value" :value="b.value">
                            {{ b.label }}
                        </option>
                    </select>
                </div>

                <!-- Tahun -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Tahun</label>
                    <select
                        v-model="filterForm.tahun"
                        class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs text-slate-800 bg-white focus:outline-none focus:ring-2 focus:ring-[#005BAC]/30 focus:border-[#005BAC]"
                    >
                        <option value="all">-- Semua Tahun --</option>
                        <option v-for="y in years" :key="y" :value="y.toString()">
                            {{ y }}
                        </option>
                    </select>
                </div>

                <!-- Status Persetujuan -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Status Persetujuan</label>
                    <select
                        v-model="filterForm.status"
                        class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs text-slate-800 bg-white focus:outline-none focus:ring-2 focus:ring-[#005BAC]/30 focus:border-[#005BAC]"
                    >
                        <option value="all">-- Semua Status --</option>
                        <option value="0">Menunggu Persetujuan</option>
                        <option value="1">Disetujui (Approved)</option>
                        <option value="2">Ditolak (Rejected)</option>
                    </select>
                </div>

                <!-- Pencarian NIP / Nama -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">NIP / Nama Pegawai</label>
                    <div class="relative">
                        <input
                            v-model="filterForm.q"
                            type="text"
                            placeholder="Cari NIP atau nama..."
                            class="w-full pl-8 pr-3 py-2 rounded-xl border border-slate-300 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#005BAC]/30 focus:border-[#005BAC]"
                        />
                        <i class="pi pi-search absolute left-2.5 top-2.5 text-slate-400 text-xs" />
                    </div>
                </div>

                <!-- Tombol Filter & Reset -->
                <div class="flex items-center gap-2">
                    <button
                        type="submit"
                        class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-[#005BAC] text-white hover:bg-[#004785] font-semibold text-xs shadow-sm transition-colors"
                    >
                        <i class="pi pi-filter text-[11px]" />
                        <span>Filter</span>
                    </button>
                    <button
                        type="button"
                        class="px-3 py-2 rounded-xl border border-slate-300 bg-slate-50 hover:bg-slate-100 text-slate-700 font-semibold text-xs transition-colors"
                        title="Reset Filter"
                        @click="resetFilter"
                    >
                        <i class="pi pi-refresh text-[11px]" />
                    </button>
                </div>
            </form>
        </div>

        <!-- ================= TABEL DATA REKAPITULASI ================= -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-600 font-bold tracking-wider uppercase text-[11px]">
                            <th class="py-3 px-3.5 text-center w-12">No</th>
                            <th class="py-3 px-3.5">Kode Izin</th>
                            <th class="py-3 px-3.5">Pegawai & Unit</th>
                            <th class="py-3 px-3.5">Tanggal</th>
                            <th class="py-3 px-3.5">Waktu Izin</th>
                            <th class="py-3 px-3.5 max-w-xs">Keperluan / Alasan</th>
                            <th class="py-3 px-3.5 text-center">Status</th>
                            <th class="py-3 px-3.5 text-center">Diajukan</th>
                            <th class="py-3 px-3.5 text-center w-36">Aksi Persetujuan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <tr
                            v-for="(item, index) in permits.data"
                            :key="item.id"
                            class="hover:bg-slate-50/60 transition-colors"
                        >
                            <!-- No -->
                            <td class="py-3 px-3.5 text-center text-slate-400 font-mono text-[11px]">
                                {{ (permits.current_page - 1) * permits.per_page + index + 1 }}
                            </td>

                            <!-- Kode Izin -->
                            <td class="py-3 px-3.5 whitespace-nowrap">
                                <span class="font-mono font-bold text-slate-800 text-[11px] block">
                                    {{ item.kode_izin }}
                                </span>
                            </td>

                            <!-- Pegawai & Unit -->
                            <td class="py-3 px-3.5">
                                <div class="font-bold text-slate-800">
                                    {{ item.nama_pegawai }}
                                </div>
                                <div class="text-[11px] text-slate-500 font-mono flex items-center gap-1.5 mt-0.5">
                                    <span class="bg-slate-100 px-1 py-0.5 rounded text-[10px] text-slate-600 font-semibold">{{ item.nip }}</span>
                                    <span>•</span>
                                    <span class="truncate max-w-[150px]">{{ item.bagian }}</span>
                                </div>
                            </td>

                            <!-- Tanggal -->
                            <td class="py-3 px-3.5 font-mono text-slate-700 whitespace-nowrap font-medium">
                                {{ item.date }}
                            </td>

                            <!-- Waktu Izin -->
                            <td class="py-3 px-3.5 whitespace-nowrap">
                                <div v-if="item.exit_time === '00:00'" class="space-y-0.5">
                                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-200">
                                        <i class="pi pi-hourglass text-[10px]" />
                                        <span>Datang Terlambat</span>
                                    </span>
                                    <div class="text-[10px] text-slate-500 font-mono">
                                        Est. Tiba: <strong class="text-slate-700">{{ item.return_time || '-' }}</strong>
                                    </div>
                                </div>
                                <div v-else class="space-y-0.5">
                                    <div class="font-mono text-xs font-semibold text-slate-800 flex items-center gap-1">
                                        <span>{{ item.exit_time }}</span>
                                        <i class="pi pi-arrow-right text-[9px] text-slate-400" />
                                        <span v-if="item.return_time">{{ item.return_time }}</span>
                                        <span v-else class="text-[11px] text-rose-600 font-normal italic">Pulang</span>
                                    </div>
                                    <div class="text-[10px] text-slate-400">
                                        {{ item.return_time ? 'Izin Sementara' : 'Izin Langsung Pulang' }}
                                    </div>
                                </div>
                            </td>

                            <!-- Keperluan / Alasan -->
                            <td class="py-3 px-3.5 max-w-xs text-slate-600 text-xs">
                                <p class="line-clamp-2 leading-relaxed" :title="item.reason">
                                    {{ item.reason }}
                                </p>
                            </td>

                            <!-- Status Approval -->
                            <td class="py-3 px-3.5 text-center whitespace-nowrap">
                                <span
                                    v-if="item.approval_status === 0"
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-200"
                                >
                                    <i class="pi pi-clock text-[10px]" />
                                    <span>Menunggu</span>
                                </span>
                                <span
                                    v-else-if="item.approval_status === 1"
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200"
                                >
                                    <i class="pi pi-check text-[10px]" />
                                    <span>Disetujui</span>
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-rose-50 text-rose-700 border border-rose-200"
                                >
                                    <i class="pi pi-times text-[10px]" />
                                    <span>Ditolak</span>
                                </span>
                            </td>

                            <!-- Waktu Pengajuan -->
                            <td class="py-3 px-3.5 text-center font-mono text-[11px] text-slate-400 whitespace-nowrap">
                                {{ item.created_at || '-' }}
                            </td>

                            <!-- Aksi Persetujuan -->
                            <td class="py-3 px-3.5 text-center whitespace-nowrap">
                                <div class="flex items-center justify-center gap-1.5">
                                    <!-- Jika Status Pending: Tombol Setujui & Tolak Cepat -->
                                    <template v-if="item.approval_status === 0">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs transition-colors shadow-xs"
                                            title="Setujui Izin Keluar"
                                            @click="openConfirmModal(item, 'approve')"
                                        >
                                            <i class="pi pi-check text-[10px]" />
                                            <span>Setujui</span>
                                        </button>
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 font-semibold text-xs transition-colors"
                                            title="Tolak Izin Keluar"
                                            @click="openConfirmModal(item, 'reject')"
                                        >
                                            <i class="pi pi-times text-[10px]" />
                                            <span>Tolak</span>
                                        </button>
                                    </template>

                                    <!-- Tombol Tiket / Detail -->
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-slate-200 bg-white hover:bg-slate-100 text-slate-700 text-xs font-semibold transition-colors"
                                        title="Lihat Detail / Tiket Pos Satpam"
                                        @click="openTicketModal(item)"
                                    >
                                        <i class="pi pi-ticket text-[11px] text-[#005BAC]" />
                                        <span>Tiket</span>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Empty State -->
                        <tr v-if="permits.data.length === 0">
                            <td colspan="9" class="py-12 text-center text-slate-400">
                                <i class="pi pi-inbox text-3xl mb-2 block text-slate-300" />
                                <span class="font-medium text-xs">Tidak ada data izin keluar kantor yang sesuai dengan filter pencarian.</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ================= PAGINATION BAR ================= -->
            <div
                v-if="permits.total > 0"
                class="px-5 py-3.5 border-t border-slate-200 bg-white flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-600"
            >
                <div>
                    Menampilkan <span class="font-bold font-mono">{{ permits.from || 0 }}</span> sampai
                    <span class="font-bold font-mono">{{ permits.to || 0 }}</span> dari
                    <span class="font-bold font-mono">{{ permits.total?.toLocaleString('id-ID') }}</span> pengajuan izin keluar
                </div>

                <div class="flex items-center gap-1">
                    <template v-for="(link, lIdx) in permits.links" :key="lIdx">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="px-3 py-1.5 rounded-lg border text-xs font-medium transition-colors"
                            :class="[
                                link.active
                                    ? 'bg-[#005BAC] text-white border-[#005BAC] font-bold'
                                    : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-100'
                            ]"
                            v-html="link.label"
                            preserve-scroll
                            preserve-state
                        />
                        <span
                            v-else
                            class="px-3 py-1.5 rounded-lg border border-slate-100 text-slate-300 text-xs cursor-not-allowed"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>

        <!-- ================= MODAL DIALOG KONFIRMASI (SETUJUI / TOLAK) ================= -->
        <div
            v-if="confirmDialog.show"
            class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto cursor-pointer"
            @click.self="closeConfirmModal"
        >
            <div class="bg-white rounded-2xl max-w-md w-full max-h-[90vh] flex flex-col overflow-hidden shadow-2xl border border-slate-200 my-auto cursor-default">
                <!-- Header Konfirmasi -->
                <div class="p-4 sm:p-5 border-b border-slate-100 flex items-start justify-between gap-3 shrink-0">
                    <div class="flex items-start gap-3">
                        <div
                            class="size-10 sm:size-11 rounded-2xl flex items-center justify-center shrink-0"
                            :class="confirmDialog.action === 'approve' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'"
                        >
                            <i :class="confirmDialog.action === 'approve' ? 'pi pi-check-circle text-lg sm:text-xl' : 'pi pi-exclamation-triangle text-lg sm:text-xl'" />
                        </div>
                        <div class="space-y-0.5">
                            <h3 class="text-sm sm:text-base font-bold text-slate-900">
                                {{ confirmDialog.action === 'approve' ? 'Setujui Izin Keluar Kantor?' : 'Tolak Izin Keluar Kantor?' }}
                            </h3>
                            <p class="text-[11px] sm:text-xs text-slate-500 leading-tight">
                                Konfirmasi keputusan status permohonan izin keluar pegawai berikut:
                            </p>
                        </div>
                    </div>

                    <!-- Tombol Silang (X) Cepat Tutup -->
                    <button
                        type="button"
                        class="size-8 rounded-full bg-slate-100 hover:bg-slate-200 active:bg-slate-300 text-slate-500 hover:text-slate-800 flex items-center justify-center cursor-pointer transition-colors shrink-0"
                        title="Tutup Modal"
                        @click="closeConfirmModal"
                    >
                        <i class="pi pi-times text-xs" />
                    </button>
                </div>

                <!-- Body Konfirmasi (Scrollable di layar kecil) -->
                <div class="p-4 sm:p-5 space-y-3.5 text-xs overflow-y-auto flex-1 overscroll-contain">
                    <!-- Informasi Ringkas Pegawai & Izin -->
                    <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200/80 space-y-1.5 text-xs">
                        <div class="flex justify-between items-start gap-2">
                            <span class="text-slate-500 shrink-0">Nama Pegawai:</span>
                            <span class="font-bold text-slate-800 text-right">{{ confirmDialog.item?.nama_pegawai }}</span>
                        </div>
                        <div class="flex justify-between items-start gap-2">
                            <span class="text-slate-500 shrink-0">NIP / Unit:</span>
                            <span class="font-mono text-slate-700 text-right">{{ confirmDialog.item?.nip }} - {{ confirmDialog.item?.bagian }}</span>
                        </div>
                        <div class="flex justify-between items-start gap-2">
                            <span class="text-slate-500 shrink-0">Tanggal & Waktu:</span>
                            <span class="font-mono text-slate-800 font-semibold text-right">
                                {{ confirmDialog.item?.date }} ({{ confirmDialog.item?.exit_time }} s/d {{ confirmDialog.item?.return_time || 'Pulang' }})
                            </span>
                        </div>
                        <div class="pt-1.5 border-t border-slate-200 text-slate-600 italic">
                            "{{ confirmDialog.item?.reason }}"
                        </div>
                    </div>
                </div>

                <!-- Footer Aksi Konfirmasi -->
                <div class="p-3.5 sm:p-4 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2 shrink-0">
                    <button
                        type="button"
                        class="px-4 py-2.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-100 active:bg-slate-200 text-slate-700 font-semibold text-xs transition-colors cursor-pointer"
                        :disabled="confirmDialog.processing"
                        @click="closeConfirmModal"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        class="px-4 py-2.5 rounded-xl text-white font-semibold text-xs transition-colors inline-flex items-center gap-1.5 shadow-sm cursor-pointer"
                        :class="[
                            confirmDialog.action === 'approve'
                                ? 'bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800'
                                : 'bg-rose-600 hover:bg-rose-700 active:bg-rose-800',
                            confirmDialog.processing ? 'opacity-60 cursor-not-allowed' : ''
                        ]"
                        :disabled="confirmDialog.processing"
                        @click="handleConfirmAction"
                    >
                        <i v-if="confirmDialog.processing" class="pi pi-spin pi-spinner text-xs" />
                        <i v-else :class="confirmDialog.action === 'approve' ? 'pi pi-check text-xs' : 'pi pi-times text-xs'" />
                        <span>{{ confirmDialog.action === 'approve' ? 'Ya, Setujui' : 'Ya, Tolak' }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ================= MODAL TIKET GATE PASS DIGITAL SATPAM ================= -->
        <div
            v-if="showTicketModal && activeTicket"
            class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto cursor-pointer"
            @click.self="closeTicketModal"
        >
            <div class="bg-white rounded-2xl sm:rounded-3xl max-w-sm w-full max-h-[92vh] flex flex-col overflow-hidden shadow-2xl border border-slate-200 relative my-auto cursor-default">
                <!-- Header Tiket BUMN -->
                <div class="bg-gradient-to-r from-[#005BAC] to-[#004785] p-4 sm:p-5 text-white text-center relative shrink-0">
                    <button
                        type="button"
                        class="absolute top-3.5 right-3.5 size-9 sm:size-10 rounded-full bg-white/20 hover:bg-white/30 active:bg-white/40 text-white flex items-center justify-center cursor-pointer transition-all z-20"
                        title="Tutup Tiket"
                        aria-label="Tutup Tiket"
                        @click="closeTicketModal"
                    >
                        <i class="pi pi-times text-sm font-bold" />
                    </button>
                    <div class="text-[10px] tracking-widest uppercase font-mono opacity-80 mb-0.5">Kimia Farma Plant Banjaran</div>
                    <h3 class="text-sm sm:text-base font-bold tracking-tight">TIKET GATE PASS DIGITAL</h3>
                    <div class="text-[11px] font-mono mt-1 px-2.5 py-0.5 rounded-full bg-white/20 inline-block font-bold">
                        {{ activeTicket.kode_izin }}
                    </div>
                </div>

                <!-- Badan Tiket (Scrollable di HP kecil) -->
                <div class="p-4 sm:p-5 space-y-3.5 text-xs overflow-y-auto flex-1 overscroll-contain">
                    <!-- Status Stempel -->
                    <div class="text-center">
                        <span
                            v-if="activeTicket.approval_status === 1"
                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300"
                        >
                            <i class="pi pi-check-circle" />
                            <span>IZIN DISETUJUI ATASAN</span>
                        </span>
                        <span
                            v-else-if="activeTicket.approval_status === 0"
                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-300"
                        >
                            <i class="pi pi-clock" />
                            <span>MENUNGGU PERSETUJUAN</span>
                        </span>
                        <span
                            v-else
                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800 border border-rose-300"
                        >
                            <i class="pi pi-times-circle" />
                            <span>PENGAJUAN DITOLAK</span>
                        </span>
                    </div>

                    <!-- Informasi Rinci Pegawai -->
                    <div class="border border-dashed border-slate-200 rounded-2xl p-3.5 sm:p-4 bg-slate-50/70 space-y-2">
                        <div class="flex justify-between items-start gap-2">
                            <span class="text-slate-400 shrink-0">Nama Pegawai:</span>
                            <span class="font-bold text-slate-900 text-right">{{ activeTicket.nama_pegawai }}</span>
                        </div>
                        <div class="flex justify-between items-start gap-2">
                            <span class="text-slate-400 shrink-0">NIP / Jabatan:</span>
                            <span class="font-mono text-slate-800 font-semibold text-right">{{ activeTicket.nip }} ({{ activeTicket.jabatan }})</span>
                        </div>
                        <div class="flex justify-between items-start gap-2">
                            <span class="text-slate-400 shrink-0">Bagian:</span>
                            <span class="text-slate-800 font-medium text-right">{{ activeTicket.bagian }}</span>
                        </div>
                        <div class="flex justify-between items-start gap-2">
                            <span class="text-slate-400 shrink-0">Tanggal Izin:</span>
                            <span class="font-mono font-bold text-slate-900">{{ activeTicket.date }}</span>
                        </div>
                        <div class="flex justify-between items-start gap-2">
                            <span class="text-slate-400 shrink-0">Waktu Izin:</span>
                            <span class="font-mono font-bold text-[#005BAC]">
                                {{ activeTicket.exit_time }} s/d {{ activeTicket.return_time || 'Selesai Kerja' }}
                            </span>
                        </div>
                        <div class="pt-2 border-t border-slate-200">
                            <span class="text-[10px] text-slate-400 block mb-0.5">Keperluan:</span>
                            <p class="text-slate-700 italic font-sans leading-relaxed">{{ activeTicket.reason }}</p>
                        </div>
                    </div>

                    <!-- QR Code Asli Ter-generate (Bisa discan HP / Satpam) -->
                    <div class="text-center pt-1">
                        <div class="size-44 sm:size-48 mx-auto border-2 border-slate-300 rounded-2xl p-2.5 flex flex-col items-center justify-center bg-white shadow-inner overflow-hidden">
                            <div v-if="qrSvgHtml" v-html="qrSvgHtml" class="flex items-center justify-center size-full [&>svg]:w-full [&>svg]:h-full [&>svg]:block" />
                            <div v-else class="text-slate-400 text-xs">Memuat QR Code...</div>
                        </div>
                        <span class="text-xs font-mono font-bold text-slate-800 mt-2 block tracking-wider">
                            {{ activeTicket.kode_izin }}
                        </span>
                        <span class="text-[10px] text-slate-400 mt-1 block leading-tight">
                            Tunjukkan tiket atau QR code ini kepada petugas keamanan di Pos Satpam saat keluar/masuk gerbang.
                        </span>
                    </div>
                </div>

                <!-- Footer Modal (Selalu terlihat di layar) -->
                <div class="p-3.5 sm:p-4 bg-slate-50 border-t border-slate-200 flex justify-end shrink-0">
                    <button
                        type="button"
                        class="w-full py-2.5 sm:py-3 rounded-xl bg-slate-800 hover:bg-slate-900 active:bg-slate-950 text-white font-semibold text-xs sm:text-sm transition-colors cursor-pointer shadow-sm flex items-center justify-center gap-2"
                        @click="closeTicketModal"
                    >
                        <i class="pi pi-times text-xs" />
                        <span>Tutup Tiket</span>
                    </button>
                </div>
            </div>
        </div>

    </div>
</template>
