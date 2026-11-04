<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, reactive } from 'vue';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';

defineOptions({ layout: AuthenticatedLayout });

const props = defineProps({
    todayScans: {
        type: Array,
        default: () => [],
    },
    guardInfo: {
        type: Object,
        default: () => ({ username: 'SC_KFPB', name: 'Petugas Keamanan' }),
    },
    todayDate: {
        type: String,
        required: true,
    },
});

const toast = useToast();

// Form Scan Input
const inputCode = ref('');
const isProcessing = ref(false);

// State Hasil Scan Terakhir
const lastScanResult = ref(null);
const scanError = ref('');

// Pemrosesan Scan (Bisa dari tembakan USB Scanner barcode atau input manual atau QR decode)
function submitScan(customCode = null) {
    const code = customCode || inputCode.value.trim();
    if (!code) {
        scanError.value = 'Silakan masukkan atau scan kode izin terlebih dahulu.';
        return;
    }

    scanError.value = '';
    isProcessing.value = true;

    fetch('/izin-keluar/scan/process', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'Accept': 'application/json',
        },
        body: JSON.stringify({ code: code }),
    })
    .then(async (response) => {
        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.message || 'Gagal memproses QR izin.');
        }
        return data;
    })
    .then((res) => {
        if (res.success) {
            lastScanResult.value = res;
            inputCode.value = '';
            toast.add({
                severity: 'success',
                summary: 'Scan Berhasil',
                detail: res.message,
                life: 4500,
            });
            // Refresh riwayat scan tabel
            router.reload({ only: ['todayScans'] });
        } else {
            scanError.value = res.message || 'Gagal memvalidasi izin.';
            toast.add({
                severity: 'error',
                summary: 'Gagal Scan',
                detail: res.message,
                life: 4000,
            });
        }
    })
    .catch((err) => {
        scanError.value = err.message || 'Terjadi kesalahan sistem saat memproses scan.';
        toast.add({
            severity: 'error',
            summary: 'Gagal Scan',
            detail: err.message,
            life: 4000,
        });
    })
    .finally(() => {
        isProcessing.value = false;
    });
}
</script>

<template>
    <Head title="Scanner QR Pos Satpam - Izin Keluar Kantor KFPB" />
    <Toast position="top-right" />

    <div class="space-y-6 max-w-5xl mx-auto px-3 sm:px-6 py-2">

        <!-- ================= TOP HEADER BREADCRUMB & TITLE ================= -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="space-y-1">
                <div class="flex items-center gap-2 text-xs font-medium text-slate-500">
                    <span class="text-slate-400">Pos Keamanan (Satpam)</span>
                    <span>/</span>
                    <span class="text-slate-800 font-semibold">Scanner QR Izin Keluar</span>
                </div>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
                    <i class="pi pi-qrcode text-emerald-600" />
                    <span>Scanner Gate Pass Pos Satpam</span>
                </h1>
                <p class="text-xs sm:text-sm text-slate-500">
                    Pencatatan real-time waktu keluar dan kembali pegawai di gerbang pos keamanan PT Kimia Farma Plant Banjaran.
                </p>
            </div>

            <!-- Petugas Info -->
            <div class="flex items-center gap-3 p-2.5 rounded-xl bg-slate-50 border border-slate-200/80 shrink-0 self-start sm:self-auto">
                <div class="size-9 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold text-xs">
                    <i class="pi pi-shield" />
                </div>
                <div class="text-xs">
                    <span class="font-bold text-slate-800 block">{{ guardInfo.name }}</span>
                    <span class="font-mono text-slate-500 text-[11px] block">{{ guardInfo.username }} &bull; Pos Gerbang</span>
                </div>
            </div>
        </div>

        <!-- ================= MAIN SCANNER PANEL ================= -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <!-- Panel 1: Input Scanner & Kamera -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 sm:p-6 space-y-4">
                <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                    <div class="size-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                        <i class="pi pi-camera text-base" />
                    </div>
                    <div>
                        <h2 class="text-sm sm:text-base font-bold text-slate-900">
                            Pindai Tiket Izin Keluar
                        </h2>
                        <p class="text-xs text-slate-500">
                            Tembakkan barcode scanner USB atau ketikkan kode izin pegawai.
                        </p>
                    </div>
                </div>

                <form @submit.prevent="submitScan()" class="space-y-4">
                    <div>
                        <label for="inputCode" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Kode Tiket / QR Code Izin
                        </label>
                        <div class="relative">
                            <input
                                id="inputCode"
                                v-model="inputCode"
                                type="text"
                                autofocus
                                placeholder="Contoh: IK-20261104-001 atau ID izin"
                                class="w-full pl-10 pr-4 py-3 rounded-xl border border-slate-300 text-sm font-mono font-bold text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-600 transition-all uppercase"
                                :disabled="isProcessing"
                            />
                            <i class="pi pi-qrcode absolute left-3.5 top-3.5 text-slate-400 text-base" />
                        </div>
                        <span class="text-[11px] text-slate-400 block mt-1">
                            Tip: Arahkan kursor ke kolom di atas lalu scan tiket QR pegawai.
                        </span>
                    </div>

                    <!-- Alert Error jika ada -->
                    <div v-if="scanError" class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-start gap-2">
                        <i class="pi pi-exclamation-circle text-rose-600 text-base shrink-0 mt-0.5" />
                        <span>{{ scanError }}</span>
                    </div>

                    <button
                        type="submit"
                        :disabled="isProcessing || !inputCode.trim()"
                        class="w-full inline-flex items-center justify-center gap-2 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs sm:text-sm shadow-sm transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <i v-if="isProcessing" class="pi pi-spin pi-spinner text-sm" />
                        <i v-else class="pi pi-check text-sm" />
                        <span>{{ isProcessing ? 'Memvalidasi...' : 'Catat Izin (Validasi Pos Satpam)' }}</span>
                    </button>
                </form>

                <!-- Panduan Satpam -->
                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/70 text-xs text-slate-600 space-y-1.5">
                    <div class="font-bold text-slate-800 flex items-center gap-1.5">
                        <i class="pi pi-info-circle text-blue-600" />
                        <span>Panduan Operasional Pos Satpam:</span>
                    </div>
                    <ul class="list-disc list-inside space-y-1 text-[11px] text-slate-500 pl-1">
                        <li><b>Scan Pertama:</b> Pegawai keluar gerbang &rarr; mencatat jam keluar aktual.</li>
                        <li><b>Scan Kedua:</b> Pegawai kembali masuk gerbang &rarr; mencatat jam kembali aktual (selesai).</li>
                        <li><b>Datang Terlambat:</b> Pegawai tiba di pos satpam &rarr; satu kali scan langsung selesai mencatat jam masuk.</li>
                    </ul>
                </div>
            </div>

            <!-- Panel 2: Tampilan Hasil Scan Terkini -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 sm:p-6 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                        <h2 class="text-sm sm:text-base font-bold text-slate-900 flex items-center gap-2">
                            <i class="pi pi-id-card text-[#005BAC]" />
                            <span>Hasil Validasi Tiket Terkini</span>
                        </h2>
                        <span v-if="lastScanResult" class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold">
                            Berhasil Dicatat
                        </span>
                    </div>

                    <!-- Ketika Belum Ada Scan -->
                    <div v-if="!lastScanResult" class="text-center py-12 text-slate-400 text-xs space-y-2">
                        <div class="size-16 rounded-2xl bg-slate-100 mx-auto flex items-center justify-center text-slate-400">
                            <i class="pi pi-barcode text-3xl" />
                        </div>
                        <p class="font-medium">Menunggu pemindaian tiket QR pegawai...</p>
                        <p class="text-[11px] text-slate-400 max-w-xs mx-auto">
                            Hasil pemeriksaan identitas dan waktu keluar/kembali akan tampil di sini secara instan.
                        </p>
                    </div>

                    <!-- Tampilan Kartu Hasil Scan Sukses -->
                    <div v-else class="space-y-4">
                        <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs font-semibold flex items-center gap-2">
                            <i class="pi pi-check-circle text-emerald-600 text-base" />
                            <span>{{ lastScanResult.message }}</span>
                        </div>

                        <div class="p-4 rounded-xl border border-dashed border-slate-200 bg-slate-50 space-y-2 text-xs">
                            <div class="flex justify-between">
                                <span class="text-slate-400">Kode Tiket:</span>
                                <span class="font-mono font-bold text-slate-900">{{ lastScanResult.data?.kode_izin }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Nama Pegawai:</span>
                                <span class="font-bold text-slate-900 text-right">{{ lastScanResult.data?.nama_pegawai }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">NIP / Unit:</span>
                                <span class="font-mono text-slate-700">{{ lastScanResult.data?.nip }} &bull; {{ lastScanResult.data?.bagian }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Waktu Aktual Dicatat:</span>
                                <span class="font-mono font-bold text-emerald-700 text-sm">{{ lastScanResult.data?.actual_time }} WIB</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Status Terbaru:</span>
                                <span class="font-bold text-[#005BAC]">{{ lastScanResult.data?.status_label }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-[11px] text-slate-400 text-center pt-4 border-t border-slate-100">
                    Pos Pengamanan & Satpam &bull; PT Kimia Farma Plant Banjaran
                </div>
            </div>
        </div>

        <!-- ================= TABEL RIWAYAT SCAN HARI INI ================= -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 sm:p-6 space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <i class="pi pi-list text-slate-700" />
                    <h3 class="text-sm sm:text-base font-bold text-slate-900 tracking-tight">
                        Riwayat Scan Izin Keluar Hari Ini
                    </h3>
                </div>
                <span class="text-xs text-slate-400 font-mono">
                    Tanggal: {{ todayDate }}
                </span>
            </div>

            <!-- Empty State -->
            <div v-if="todayScans.length === 0" class="text-center py-10 text-slate-400 text-xs">
                <i class="pi pi-inbox text-3xl mb-2 block text-slate-300" />
                <span>Belum ada pegawai yang discan keluar/kembali hari ini.</span>
            </div>

            <!-- Table Riwayat Scan Satpam -->
            <div v-else class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-600 font-bold uppercase text-[10px] tracking-wider">
                            <th class="py-2.5 px-3">Kode Tiket</th>
                            <th class="py-2.5 px-3">Pegawai</th>
                            <th class="py-2.5 px-3">Rencana Waktu</th>
                            <th class="py-2.5 px-3">Scan Keluar</th>
                            <th class="py-2.5 px-3">Scan Kembali</th>
                            <th class="py-2.5 px-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <tr v-for="scan in todayScans" :key="scan.id" class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-3 px-3 font-mono font-bold text-slate-800 whitespace-nowrap">
                                {{ scan.kode_izin }}
                            </td>
                            <td class="py-3 px-3">
                                <div class="font-bold text-slate-900">{{ scan.nama_pegawai }}</div>
                                <div class="text-[11px] text-slate-500 font-mono">{{ scan.nip }} &bull; {{ scan.bagian }}</div>
                            </td>
                            <td class="py-3 px-3 font-mono text-slate-600 whitespace-nowrap">
                                {{ scan.exit_time }} s/d {{ scan.return_time || 'Pulang' }}
                            </td>
                            <td class="py-3 px-3 font-mono text-slate-800 font-semibold whitespace-nowrap">
                                <span v-if="scan.actual_exit_time" class="text-emerald-700">{{ scan.actual_exit_time }}</span>
                                <span v-else class="text-slate-400">-</span>
                            </td>
                            <td class="py-3 px-3 font-mono text-slate-800 font-semibold whitespace-nowrap">
                                <span v-if="scan.actual_return_time" class="text-emerald-700">{{ scan.actual_return_time }}</span>
                                <span v-else class="text-slate-400">-</span>
                            </td>
                            <td class="py-3 px-3 text-center whitespace-nowrap">
                                <span
                                    v-if="scan.approval_status === 3"
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200"
                                >
                                    <i class="pi pi-external-link text-[9px]" />
                                    <span>Sedang Di Luar</span>
                                </span>
                                <span
                                    v-else-if="scan.approval_status === 4"
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200"
                                >
                                    <i class="pi pi-check-circle text-[9px]" />
                                    <span>Selesai</span>
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</template>

