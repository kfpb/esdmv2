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

// State filter formulir utama
const filterForm = reactive({
    bulan: props.filters.bulan || 'all',
    tahun: props.filters.tahun || new Date().getFullYear().toString(),
    jenis: props.filters.jenis || '',
    q: props.filters.q || '',
    per_page: props.filters.per_page || 25,
});

// State pencarian instan pada tabel dan seleksi baris
const instantSearch = ref('');
const selectedPermissions = ref([]);

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

const jenisOptions = computed(() => [
    { id: '', name: '-- Semua Kategori --' },
    ...props.permissionTypes,
]);

const tahunOptions = computed(() => [
    { value: 'all', label: '-- Semua Tahun --' },
    ...props.years.map((y) => ({ value: y.toString(), label: y.toString() })),
]);

// Computed filter data instan di tabel
const filteredPermissions = computed(() => {
    const list = props.permissions.data || [];
    if (!instantSearch.value.trim()) return list;
    const query = instantSearch.value.toLowerCase().trim();
    return list.filter((item) => {
        return (
            (item.nip && item.nip.toLowerCase().includes(query)) ||
            (item.nama_pegawai && item.nama_pegawai.toLowerCase().includes(query)) ||
            (item.bagian && item.bagian.toLowerCase().includes(query)) ||
            (item.nama_ijin && item.nama_ijin.toLowerCase().includes(query)) ||
            (item.keterangan && item.keterangan.toLowerCase().includes(query))
        );
    });
});

function applyFilter() {
    router.get('/admin/izin', {
        bulan: filterForm.bulan,
        tahun: filterForm.tahun,
        jenis: filterForm.jenis,
        q: filterForm.q || instantSearch.value,
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
    instantSearch.value = '';
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

function permissionSeverity(idIjin) {
    switch (parseInt(idIjin)) {
        case 2: // Sakit
            return 'danger';
        case 3: // Cuti
            return 'info';
        case 5: // Dinas Luar
            return 'success';
        default:
            return 'secondary';
    }
}

function handleExportPdf() {
    const dataToExport = filteredPermissions.value;
    if (!dataToExport || dataToExport.length === 0) {
        alert('Tidak ada data izin yang dapat diekspor.');
        return;
    }

    const bulanObj = bulanList.find((b) => b.value === filterForm.bulan);
    const bulanLabel = bulanObj ? bulanObj.label : 'Semua Bulan';
    const tahunLabel = filterForm.tahun === 'all' ? 'Semua Tahun' : filterForm.tahun;
    const selectedType = props.permissionTypes.find((pt) => pt.id.toString() === filterForm.jenis);
    const jenisLabel = selectedType ? selectedType.name : 'Semua Kategori';

    exportTableToPdf({
        title: 'REKAPITULASI LAPORAN IZIN KETIDAKHADIRAN PEGAWAI',
        subtitle: 'Sistem Elektronik Sumber Daya Manusia (eSDM) - Plant Banjaran',
        documentNo: 'KFPB-SDM-IZIN-01',
        metaInfo: [
            { label: 'Bulan', value: bulanLabel },
            { label: 'Tahun', value: tahunLabel },
            { label: 'Kategori Izin', value: jenisLabel },
            { label: 'Kata Kunci', value: instantSearch.value || filterForm.q || 'Semua Data' },
        ],
        columns: [
            { header: 'No', width: '35px', align: 'center', render: (row, idx) => idx + 1 },
            { header: 'NIP', field: 'nip', width: '75px' },
            { header: 'Nama Pegawai', field: 'nama_pegawai' },
            { header: 'Bagian', field: 'bagian' },
            { header: 'Kategori', field: 'nama_ijin', width: '90px' },
            {
                header: 'Periode Tanggal',
                render: (row) => row.tgl_awal === row.tgl_akhir ? row.tgl_awal : `${row.tgl_awal} s/d ${row.tgl_akhir}`,
            },
            { header: 'Durasi', align: 'center', width: '60px', render: (row) => `${row.durasi} Hari` },
            { header: 'Keterangan', field: 'keterangan' },
        ],
        data: dataToExport,
    });
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
                <Button
                    label="Export PDF"
                    icon="pi pi-file-pdf"
                    severity="danger"
                    outlined
                    size="small"
                    @click="handleExportPdf"
                />
                <Link
                    href="/izin/buat"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-[#005BAC] text-white hover:bg-[#004785] font-semibold text-xs sm:text-sm transition-colors shadow-sm"
                >
                    <i class="pi pi-plus text-xs" />
                    <span>Input Izin Manual</span>
                </Link>
            </div>
        </div>

        <!-- ================= 4 KARTU STATISTIK RINGKAS ================= -->
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
                    <span class="text-[10px] text-slate-400 block mt-0.5">Surat Dokter</span>
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
                    <span class="text-[10px] text-slate-400 block mt-0.5">Tugas Kantor</span>
                </div>
                <div class="size-10 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600 border border-emerald-100">
                    <i class="pi pi-briefcase text-base" />
                </div>
            </div>
        </div>

        <!-- ================= CARD DATATABLE STANDAR /DATATABLE ================= -->
        <Card class="shadow-sm border border-slate-200/80 rounded-2xl overflow-hidden">
            <!-- Toolbar Header -->
            <template #header>
                <div class="flex flex-col gap-4 px-6 pt-5 pb-4 border-b border-slate-200/80 bg-slate-50/50">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <i class="pi pi-table text-[#005BAC] text-sm" />
                            <span class="text-sm font-bold text-slate-800 uppercase tracking-wider">
                                Rekapitulasi Izin Tidak Hadir
                            </span>
                            <span class="px-2.5 py-0.5 rounded-full bg-slate-200 text-slate-700 text-xs font-mono font-bold">
                                {{ permissions.total?.toLocaleString('id-ID') || filteredPermissions.length }} Data
                            </span>
                        </div>

                        <!-- Export PDF & Reset Toolbar Button -->
                        <div class="flex items-center gap-2">
                            <span v-if="selectedPermissions.length" class="text-xs text-slate-600 font-medium">
                                {{ selectedPermissions.length }} terpilih
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

                    <!-- Filter Controls Grid -->
                    <div class="flex flex-wrap items-center gap-2.5">
                        <InputText
                            v-model="instantSearch"
                            placeholder="Cari NIP, nama, bagian..."
                            class="w-full sm:w-60"
                        >
                            <template #prefix><i class="pi pi-search text-slate-400" /></template>
                        </InputText>

                        <Select
                            v-model="filterForm.jenis"
                            :options="jenisOptions"
                            optionLabel="name"
                            optionValue="id"
                            placeholder="Kategori Izin"
                            class="w-full sm:w-44"
                            @change="applyFilter"
                        />

                        <Select
                            v-model="filterForm.bulan"
                            :options="bulanList"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Pilih Bulan"
                            class="w-full sm:w-40"
                            @change="applyFilter"
                        />

                        <Select
                            v-model="filterForm.tahun"
                            :options="tahunOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Pilih Tahun"
                            class="w-full sm:w-36"
                            @change="applyFilter"
                        />

                        <Button
                            label="Filter"
                            icon="pi pi-filter"
                            size="small"
                            @click="applyFilter"
                        />
                        <Button
                            icon="pi pi-refresh"
                            severity="secondary"
                            outlined
                            size="small"
                            title="Reset Filter"
                            @click="resetFilter"
                        />
                    </div>
                </div>
            </template>

            <!-- Content Datatable -->
            <template #content>
                <DataTable
                    v-model:selection="selectedPermissions"
                    :value="filteredPermissions"
                    dataKey="id"
                    paginator
                    :rows="10"
                    :rowsPerPageOptions="[10, 25, 50, 100]"
                    sortMode="multiple"
                    removableSort
                    stripedRows
                    tableStyle="min-width: 65rem"
                >
                    <Column selectionMode="multiple" headerStyle="width: 3rem" />
                    <Column field="id" header="No" sortable style="width: 4rem" class="text-center">
                        <template #body="{ index }">
                            <span class="font-mono text-slate-400 text-xs">
                                {{ (permissions.current_page - 1) * permissions.per_page + index + 1 }}
                            </span>
                        </template>
                    </Column>
                    <Column field="nip" header="NPP / NIP" sortable style="width: 7.5rem">
                        <template #body="{ data }">
                            <span class="font-mono font-bold text-slate-900">{{ data.nip }}</span>
                        </template>
                    </Column>
                    <Column field="nama_pegawai" header="Nama Pegawai" sortable>
                        <template #body="{ data }">
                            <span class="font-semibold text-slate-800">{{ data.nama_pegawai }}</span>
                        </template>
                    </Column>
                    <Column field="bagian" header="Bagian" sortable>
                        <template #body="{ data }">
                            <span class="text-slate-600 text-xs">{{ data.bagian }}</span>
                        </template>
                    </Column>
                    <Column field="nama_ijin" header="Kategori Izin" sortable style="width: 8rem">
                        <template #body="{ data }">
                            <Tag :value="data.nama_ijin" :severity="permissionSeverity(data.id_ijin)" rounded />
                        </template>
                    </Column>
                    <Column field="tgl_awal" header="Periode Tanggal" sortable style="width: 10rem">
                        <template #body="{ data }">
                            <div class="font-mono text-xs text-slate-600 whitespace-nowrap">
                                <span>{{ data.tgl_awal }}</span>
                                <span v-if="data.tgl_awal !== data.tgl_akhir" class="text-slate-400"> s/d {{ data.tgl_akhir }}</span>
                            </div>
                        </template>
                    </Column>
                    <Column field="durasi" header="Durasi" sortable class="text-center" style="width: 6rem">
                        <template #body="{ data }">
                            <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-800 font-mono text-[11px] font-semibold">
                                {{ data.durasi }} Hari
                            </span>
                        </template>
                    </Column>
                    <Column field="keterangan" header="Keterangan">
                        <template #body="{ data }">
                            <span class="line-clamp-2 text-slate-600 text-xs" :title="data.keterangan">
                                {{ data.keterangan || '-' }}
                            </span>
                        </template>
                    </Column>
                    <Column header="Lampiran" class="text-center" style="width: 5.5rem">
                        <template #body="{ data }">
                            <button
                                v-if="data.lampiran_url"
                                type="button"
                                class="size-8 rounded-lg border border-slate-200 overflow-hidden hover:ring-2 hover:ring-[#005BAC]/50 transition-all shadow-2xs inline-flex items-center justify-center cursor-pointer"
                                title="Lihat Berkas Lampiran"
                                @click="openDetail(data)"
                            >
                                <img
                                    :src="data.lampiran_url"
                                    alt="Lampiran"
                                    class="size-full object-cover"
                                />
                            </button>
                            <span v-else class="text-slate-300 text-xs">-</span>
                        </template>
                    </Column>
                    <Column header="Aksi" class="text-center" style="width: 6.5rem">
                        <template #body="{ data }">
                            <Button
                                icon="pi pi-eye"
                                label="Detail"
                                text
                                size="small"
                                severity="info"
                                @click="openDetail(data)"
                            />
                        </template>
                    </Column>
                </DataTable>

                <!-- Server Pagination Links jika data bertingkat per halaman -->
                <div
                    v-if="permissions.total > permissions.per_page"
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
            </template>
        </Card>

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
                                NIP: {{ activeDetail.nip }} &bull; {{ activeDetail.nama_pegawai }}
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="size-8 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-200/60 flex items-center justify-center transition-colors cursor-pointer"
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
                                <span>Buka Ukuran Asli</span>
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
                        class="px-4 py-2 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-700 font-semibold text-xs transition-colors cursor-pointer"
                        @click="closeDetail"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>

    </div>
</template>
