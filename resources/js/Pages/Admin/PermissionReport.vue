<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, reactive } from 'vue';

defineOptions({ layout: AuthenticatedLayout });

const props = defineProps({
    permissions: {
        type: Object,
        required: true,
    },
    permissionTypes: {
        type: Array,
        default: () => [],
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
            jenis: '',
            q: '',
            per_page: 25,
        }),
    },
    statistics: {
        type: Object,
        default: () => ({
            total: 0,
            sakit: 0,
            cuti: 0,
            dinas: 0,
        }),
    },
});

// State filter formulir
const filterForm = reactive({
    bulan: props.filters.bulan || 'all',
    tahun: props.filters.tahun || new Date().getFullYear().toString(),
    jenis: props.filters.jenis || '',
    q: props.filters.q || '',
    per_page: props.filters.per_page || 25,
});

// State Modal Detail & Pratinjau Lampiran
const showDetailModal = ref(false);
const activeDetail = ref(null);

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
    router.get('/admin/izin', {
        bulan: filterForm.bulan,
        tahun: filterForm.tahun,
        jenis: filterForm.jenis,
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
    filterForm.jenis = '';
    filterForm.q = '';
    filterForm.per_page = 25;
    applyFilter();
}

function openDetail(item) {
    activeDetail.value = item;
    showDetailModal.value = true;
}

function closeDetail() {
    showDetailModal.value = false;
    activeDetail.value = null;
}

function getBadgeStyle(idIjin) {
    switch (parseInt(idIjin)) {
        case 2: // Sakit
            return 'bg-rose-50 text-rose-700 border-rose-200';
        case 3: // Cuti
            return 'bg-blue-50 text-blue-700 border-blue-200';
        case 5: // Dinas Luar
            return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        default:
            return 'bg-slate-100 text-slate-700 border-slate-200';
    }
}
</script>

<template>
    <Head title="Rekapitulasi Izin Tidak Hadir Pegawai - Admin eSDM KFPB" />

    <div class="space-y-5 max-w-7xl mx-auto px-3 sm:px-6 py-2">

        <!-- ================= TOP HEADER BREADCRUMB & TITLE ================= -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="space-y-1">
                <div class="flex items-center gap-2 text-xs font-medium text-slate-500">
                    <span class="text-slate-400">Pusat Verifikasi</span>
                    <span>/</span>
                    <span class="text-slate-800 font-semibold">Data Izin Tidak Hadir</span>
                </div>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
                    Rekapitulasi Izin Tidak Hadir Pegawai
                </h1>
                <p class="text-xs sm:text-sm text-slate-500">
                    Daftar pelaporan izin sakit, cuti, dinas luar, dan ketidakhadiran yang tercatat di sistem presensi KFPB.
                </p>
            </div>

            <!-- Tombol Aksi Cepat Admin -->
            <div class="flex items-center gap-2 shrink-0 self-start sm:self-auto">
                <Link
                    href="/izin/buat"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#005BAC] text-white hover:bg-[#004785] font-semibold text-xs sm:text-sm transition-colors shadow-sm"
                >
                    <i class="pi pi-plus text-xs" />
                    <span>Input Izin Manual</span>
                </Link>
            </div>
        </div>

        <!-- ================= 4 KARTU STATISTIK RINGKAS (TANPA GRAFIK LEBAY) ================= -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4">
            <!-- Total Izin -->
            <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Total Laporan</span>
                    <span class="text-xl sm:text-2xl font-bold font-mono text-slate-800">{{ statistics.total.toLocaleString('id-ID') }}</span>
                    <span class="text-[10px] text-slate-400 block mt-0.5">Sesuai filter aktif</span>
                </div>
                <div class="size-10 rounded-xl bg-slate-100 flex items-center justify-center text-slate-600">
                    <i class="pi pi-file text-base" />
                </div>
            </div>

            <!-- Sakit -->
            <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold text-rose-500 uppercase tracking-wider block">Izin Sakit</span>
                    <span class="text-xl sm:text-2xl font-bold font-mono text-slate-800">{{ statistics.sakit.toLocaleString('id-ID') }}</span>
                    <span class="text-[10px] text-slate-400 block mt-0.5">Surat Keterangan Dokter</span>
                </div>
                <div class="size-10 rounded-xl bg-rose-50 flex items-center justify-center text-rose-600 border border-rose-100">
                    <i class="pi pi-heart text-base" />
                </div>
            </div>

            <!-- Cuti -->
            <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold text-blue-600 uppercase tracking-wider block">Izin Cuti</span>
                    <span class="text-xl sm:text-2xl font-bold font-mono text-slate-800">{{ statistics.cuti.toLocaleString('id-ID') }}</span>
                    <span class="text-[10px] text-slate-400 block mt-0.5">Tahunan & Bersama</span>
                </div>
                <div class="size-10 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600 border border-blue-100">
                    <i class="pi pi-calendar-minus text-base" />
                </div>
            </div>

            <!-- Dinas Luar -->
            <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold text-emerald-600 uppercase tracking-wider block">Dinas Luar</span>
                    <span class="text-xl sm:text-2xl font-bold font-mono text-slate-800">{{ statistics.dinas.toLocaleString('id-ID') }}</span>
                    <span class="text-[10px] text-slate-400 block mt-0.5">Tugas Kantor / Luar Kota</span>
                </div>
                <div class="size-10 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600 border border-emerald-100">
                    <i class="pi pi-briefcase text-base" />
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

                <!-- Jenis Izin -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Jenis Izin</label>
                    <select
                        v-model="filterForm.jenis"
                        class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs text-slate-800 bg-white focus:outline-none focus:ring-2 focus:ring-[#005BAC]/30 focus:border-[#005BAC]"
                    >
                        <option value="">-- Semua Kategori --</option>
                        <option v-for="pt in permissionTypes" :key="pt.id" :value="pt.id.toString()">
                            {{ pt.name }}
                        </option>
                    </select>
                </div>

                <!-- Pencarian NPP / Nama -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">NPP / Nama Pegawai</label>
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
            <!-- Table Sub-header -->
            <div class="px-5 py-3.5 border-b border-slate-200 bg-slate-50/70 flex items-center justify-between flex-wrap gap-2">
                <div class="flex items-center gap-2">
                    <i class="pi pi-list text-slate-500 text-xs" />
                    <span class="text-xs font-bold text-slate-700 uppercase tracking-wider">
                        Daftar Riwayat Izin Pegawai
                    </span>
                    <span class="px-2 py-0.5 rounded-full bg-slate-200 text-slate-700 text-[10px] font-mono font-bold">
                        {{ permissions.total?.toLocaleString('id-ID') || 0 }} Baris
                    </span>
                </div>

                <!-- Per Page Selector -->
                <div class="flex items-center gap-2 text-xs text-slate-500">
                    <span>Tampilkan</span>
                    <select
                        v-model="filterForm.per_page"
                        class="px-2 py-1 rounded-lg border border-slate-300 text-xs bg-white focus:outline-none"
                        @change="applyFilter"
                    >
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                    </select>
                    <span>per halaman</span>
                </div>
            </div>

            <!-- Table Responsive Container -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-700">
                    <thead class="bg-slate-100/70 border-b border-slate-200 text-slate-500 font-bold uppercase text-[10px] tracking-wider">
                        <tr>
                            <th class="py-3 px-3.5 text-center w-12">No</th>
                            <th class="py-3 px-3.5">NPP / NIP</th>
                            <th class="py-3 px-3.5">Nama Pegawai</th>
                            <th class="py-3 px-3.5">Bagian</th>
                            <th class="py-3 px-3.5">Kategori Izin</th>
                            <th class="py-3 px-3.5">Periode Tanggal</th>
                            <th class="py-3 px-3.5 text-center">Durasi</th>
                            <th class="py-3 px-3.5 max-w-xs">Keterangan</th>
                            <th class="py-3 px-3.5 text-center w-24">Lampiran</th>
                            <th class="py-3 px-3.5 text-center w-20">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="(item, idx) in permissions.data"
                            :key="item.id"
                            class="hover:bg-slate-50/80 transition-colors"
                        >
                            <!-- No -->
                            <td class="py-3 px-3.5 text-center text-slate-400 font-mono text-[11px]">
                                {{ (permissions.current_page - 1) * permissions.per_page + idx + 1 }}
                            </td>

                            <!-- NIP -->
                            <td class="py-3 px-3.5 font-mono font-bold text-slate-900">
                                {{ item.nip }}
                            </td>

                            <!-- Nama -->
                            <td class="py-3 px-3.5 font-medium text-slate-800">
                                {{ item.nama_pegawai }}
                            </td>

                            <!-- Bagian -->
                            <td class="py-3 px-3.5 text-slate-600">
                                {{ item.bagian }}
                            </td>

                            <!-- Jenis Izin (Badge) -->
                            <td class="py-3 px-3.5">
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold border"
                                    :class="getBadgeStyle(item.id_ijin)"
                                >
                                    {{ item.nama_ijin }}
                                </span>
                            </td>

                            <!-- Tanggal -->
                            <td class="py-3 px-3.5 font-mono text-slate-600 whitespace-nowrap">
                                <span>{{ item.tgl_awal }}</span>
                                <span v-if="item.tgl_awal !== item.tgl_akhir" class="text-slate-400"> s/d {{ item.tgl_akhir }}</span>
                            </td>

                            <!-- Durasi -->
                            <td class="py-3 px-3.5 text-center font-semibold text-slate-700 whitespace-nowrap">
                                <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-800 font-mono text-[11px]">
                                    {{ item.durasi }} Hari
                                </span>
                            </td>

                            <!-- Keterangan -->
                            <td class="py-3 px-3.5 max-w-xs text-slate-600 truncate" :title="item.keterangan">
                                {{ item.keterangan }}
                            </td>

                            <!-- Lampiran Foto / Bukti -->
                            <td class="py-3 px-3.5 text-center">
                                <div v-if="item.lampiran_url" class="inline-flex items-center justify-center">
                                    <button
                                        type="button"
                                        class="group relative size-9 rounded-lg border border-slate-200 overflow-hidden hover:ring-2 hover:ring-[#005BAC]/50 transition-all shadow-xs"
                                        title="Klik untuk melihat lampiran"
                                        @click="openDetail(item)"
                                    >
                                        <img
                                            :src="item.lampiran_url"
                                            alt="Bukti Izin"
                                            class="size-full object-cover group-hover:scale-110 transition-transform duration-200"
                                        />
                                    </button>
                                </div>
                                <span v-else class="text-slate-400 text-xs">-</span>
                            </td>

                            <!-- Aksi -->
                            <td class="py-3 px-3.5 text-center">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-slate-200 bg-white hover:bg-slate-100 text-slate-700 text-xs font-semibold transition-colors"
                                    title="Lihat Detail Izin"
                                    @click="openDetail(item)"
                                >
                                    <i class="pi pi-eye text-[11px]" />
                                    <span>Detail</span>
                                </button>
                            </td>
                        </tr>

                        <!-- Empty State -->
                        <tr v-if="permissions.data.length === 0">
                            <td colspan="10" class="py-12 text-center text-slate-400">
                                <i class="pi pi-inbox text-3xl mb-2 block text-slate-300" />
                                <span class="font-medium text-xs">Tidak ada data izin yang sesuai dengan filter saat ini.</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ================= PAGINATION BAR ================= -->
            <div
                v-if="permissions.total > 0"
                class="px-5 py-3.5 border-t border-slate-200 bg-white flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-600"
            >
                <div>
                    Menampilkan <span class="font-bold font-mono">{{ permissions.from || 0 }}</span> sampai
                    <span class="font-bold font-mono">{{ permissions.to || 0 }}</span> dari
                    <span class="font-bold font-mono">{{ permissions.total?.toLocaleString('id-ID') }}</span> data izin
                </div>

                <div class="flex items-center gap-1">
                    <template v-for="(link, lIdx) in permissions.links" :key="lIdx">
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
                            class="px-3 py-1.5 rounded-lg border border-slate-100 text-slate-300 text-xs"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>

        <!-- ================= MODAL DETAIL & PRATINJAU LAMPIRAN BUKTI ================= -->
        <div
            v-if="showDetailModal && activeDetail"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs transition-all"
        >
            <div class="relative w-full max-w-2xl bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden flex flex-col max-h-[90vh]">
                <!-- Modal Header -->
                <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center gap-2.5">
                        <div class="size-8 rounded-lg bg-[#005BAC]/10 text-[#005BAC] flex items-center justify-center">
                            <i class="pi pi-file-check text-sm" />
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-slate-900">
                                Detail Izin Tidak Hadir Pegawai
                            </h3>
                            <p class="text-[11px] text-slate-500">
                                NIP: {{ activeDetail.nip }} • {{ activeDetail.nama_pegawai }}
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="size-8 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-200/60 flex items-center justify-center transition-colors"
                        @click="closeDetail"
                    >
                        <i class="pi pi-times text-sm" />
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-5 space-y-4 overflow-y-auto">
                    <!-- Info Grid -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5 rounded-xl bg-slate-50 border border-slate-200/80 text-xs">
                        <div>
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">Kategori Izin</span>
                            <span class="font-bold text-slate-800">{{ activeDetail.nama_ijin }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">Bagian</span>
                            <span class="font-semibold text-slate-700 truncate block">{{ activeDetail.bagian }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">Periode</span>
                            <span class="font-semibold text-slate-700 font-mono">{{ activeDetail.tgl_awal }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">Durasi</span>
                            <span class="font-bold text-[#005BAC]">{{ activeDetail.durasi }} Hari Kerja</span>
                        </div>
                    </div>

                    <!-- Keterangan Alasan -->
                    <div class="space-y-1">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-600">Keterangan / Alasan</span>
                        <div class="p-3 rounded-xl bg-white border border-slate-200 text-xs text-slate-700 leading-relaxed whitespace-pre-wrap">
                            {{ activeDetail.keterangan || 'Tidak ada keterangan tertulis.' }}
                        </div>
                    </div>

                    <!-- Lampiran Bukti Dokumen -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-600">
                                Berkas Lampiran Bukti
                            </span>
                            <a
                                v-if="activeDetail.lampiran_url"
                                :href="activeDetail.lampiran_url"
                                target="_blank"
                                class="inline-flex items-center gap-1 text-xs text-[#005BAC] hover:underline font-semibold"
                            >
                                <i class="pi pi-external-link text-[11px]" />
                                <span>Buka Ukuran Asli / Tab Baru</span>
                            </a>
                        </div>

                        <!-- Gambar Lampiran -->
                        <div v-if="activeDetail.lampiran_url" class="border border-slate-200 rounded-xl overflow-hidden bg-slate-100 flex items-center justify-center p-2">
                            <img
                                :src="activeDetail.lampiran_url"
                                alt="Berkas Lampiran"
                                class="max-h-80 w-auto object-contain rounded-lg shadow-xs"
                            />
                        </div>
                        <div v-else class="p-4 rounded-xl border border-slate-200 bg-slate-50 text-center text-xs text-slate-400">
                            Pegawai tidak menyertakan lampiran foto / dokumen pada pengajuan ini.
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-5 py-3.5 border-t border-slate-200 bg-slate-50 flex items-center justify-between">
                    <span class="text-[11px] text-slate-400">
                        Dicatat pada: {{ activeDetail.created_at || '-' }}
                    </span>
                    <button
                        type="button"
                        class="px-4 py-2 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-700 font-semibold text-xs transition-colors"
                        @click="closeDetail"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>

    </div>
</template>

