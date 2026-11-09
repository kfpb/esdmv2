<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, reactive, computed } from 'vue';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import ConfirmPopup from 'primevue/confirmpopup';
import { useConfirm } from 'primevue/useconfirm';

defineOptions({ layout: AuthenticatedLayout });

const props = defineProps({
    documents: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            per_page: 25,
        }),
    },
});

const toast = useToast();
const confirm = useConfirm();

// ===================== FILTER =====================
const filterForm = reactive({
    search: props.filters.search || '',
    per_page: props.filters.per_page || 25,
});

function applyFilter() {
    router.get('/admin/master_data/document', {
        search: filterForm.search,
        per_page: filterForm.per_page,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
}

function resetFilter() {
    filterForm.search = '';
    filterForm.per_page = 25;
    applyFilter();
}

// ===================== MODAL CREATE / EDIT =====================
const showModal = ref(false);
const isEdit = ref(false);
const editingId = ref(null);

const form = useForm({
    name: '',
});

const modalTitle = computed(() => {
    return isEdit.value ? 'Edit Data Berkas' : 'Input Data Berkas';
});

function openCreate() {
    isEdit.value = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
}

function openEdit(item) {
    isEdit.value = true;
    editingId.value = item.id;
    form.name = item.name;
    form.clearErrors();
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    form.reset();
    form.clearErrors();
    editingId.value = null;
}

function submitForm() {
    if (isEdit.value) {
        form.put(`/admin/master_data/document/${editingId.value}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast.add({
                    severity: 'success',
                    summary: 'Berhasil',
                    detail: 'Data Berkas berhasil diperbarui.',
                    life: 3000,
                });
                closeModal();
            },
            onError: () => {
                toast.add({
                    severity: 'error',
                    summary: 'Gagal',
                    detail: 'Terjadi kesalahan saat memperbarui data berkas.',
                    life: 3000,
                });
            },
        });
    } else {
        form.post('/admin/master_data/document', {
            preserveScroll: true,
            onSuccess: () => {
                toast.add({
                    severity: 'success',
                    summary: 'Berhasil',
                    detail: 'Data Berkas berhasil ditambahkan.',
                    life: 3000,
                });
                closeModal();
            },
            onError: () => {
                toast.add({
                    severity: 'error',
                    summary: 'Gagal',
                    detail: 'Terjadi kesalahan saat menambahkan data berkas.',
                    life: 3000,
                });
            },
        });
    }
}

function destroyDocument(event, item) {
    confirm.require({
        target: event.currentTarget, // penting! supaya muncul di dekat tombol
        message: `Yakin ingin menghapus berkas "${item.name}"?`,
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Hapus',
        rejectLabel: 'Batal',
        acceptClass: 'p-button-danger p-button-sm',
        rejectClass: 'p-button-text p-button-sm',
        accept: () => {
            router.delete(`/admin/master_data/document/${item.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    toast.add({
                        severity: 'success',
                        summary: 'Berhasil',
                        detail: `Berkas "${item.name}" berhasil dihapus.`,
                        life: 3000,
                    });
                },
                onError: (errors) => {
                    toast.add({
                        severity: 'error',
                        summary: 'Gagal',
                        detail: errors.delete || 'Terjadi kesalahan saat menghapus data.',
                        life: 4000,
                    });
                },
            });
        },
        reject: () => {
            // Tidak melakukan apa-apa jika dibatalkan
        },
    });
}
</script>

<template>
    <Head title="Data Berkas - Admin eSDM KFPB" />

    <div class="space-y-5 max-w-7xl mx-auto px-3 sm:px-6 py-2">
        <Toast position="top-right" />
        <ConfirmPopup></ConfirmPopup>

        <!-- ================= TOP HEADER ================= -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="space-y-1">
                <div class="flex items-center gap-2 text-xs font-medium text-slate-500">
                    <span class="text-slate-400">Master Data</span>
                    <span>/</span>
                    <span class="text-slate-800 font-semibold">Data Agama</span>
                </div>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
                    Data Berkas
                </h1>
                <p class="text-xs sm:text-sm text-slate-500">
                    Halaman ini menampilkan daftar data berkas pegawai yang terdaftar di sistem eSDM KFPB.
                </p>
            </div>

            <div class="flex items-center gap-2 shrink-0 self-start sm:self-auto">
                <button
                    type="button"
                    @click="openCreate"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#005BAC] text-white hover:bg-[#004785] font-semibold text-xs sm:text-sm transition-colors shadow-sm"
                >
                    <i class="pi pi-plus text-xs" />
                    <span>Input Data Berkas</span>
                </button>
            </div>
        </div>

        <!-- ================= PANEL FILTER ================= -->
        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-sm">
            <form @submit.prevent="applyFilter" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3 items-end">
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Cari</label>
                    <div class="relative">
                        <input
                            v-model="filterForm.search"
                            type="text"
                            placeholder="Cari Agama..."
                            class="w-full pl-8 pr-3 py-2 rounded-xl border border-slate-300 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#005BAC]/30 focus:border-[#005BAC]"
                        />
                        <i class="pi pi-search absolute left-2.5 top-2.5 text-slate-400 text-xs" />
                    </div>
                </div>

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

        <!-- ================= TABEL DATA ================= -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <!-- Table Header -->
            <div class="px-5 py-3.5 border-b border-slate-200 bg-slate-50/70 flex items-center justify-between flex-wrap gap-2">
                <div class="flex items-center gap-2">
                    <i class="pi pi-list text-slate-500 text-xs" />
                    <span class="text-xs font-bold text-slate-700 uppercase tracking-wider">
                        Daftar Berkas
                    </span>
                    <span class="px-2 py-0.5 rounded-full bg-slate-200 text-slate-700 text-[10px] font-mono font-bold">
                        {{ documents.total?.toLocaleString('id-ID') || 0 }} Baris
                    </span>
                </div>

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

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-700">
                    <thead class="bg-slate-100/70 border-b border-slate-200 text-slate-500 font-bold uppercase text-[10px] tracking-wider">
                        <tr>
                            <th class="py-3 px-3.5 text-center w-12">No</th>
                            <th class="py-3 px-3.5">Agama</th>
                            <th class="py-3 px-3.5 text-center w-32">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="(item, idx) in documents.data"
                            :key="item.id"
                            class="hover:bg-slate-50/80 transition-colors"
                        >
                            <td class="py-3 px-3.5 text-center text-slate-400 font-mono text-[11px]">
                                {{ (documents.current_page - 1) * documents.per_page + idx + 1 }}
                            </td>
                            <td class="py-3 px-3.5 font-medium text-slate-800">
                                {{ item.name }}
                            </td>
                            <td class="py-3 px-3.5 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button
                                        type="button"
                                        @click="openEdit(item)"
                                        class="severity-warning inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-yellow-200 bg-white hover:bg-yellow-50 text-yellow-600 text-xs font-semibold transition-colors"
                                        title="Edit"
                                    >
                                        <i class="pi pi-pencil text-[11px]" />
                                    </button>
                                    <button
                                        type="button"
                                        @click="destroyDocument($event, item)"
                                        class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-red-200 bg-white hover:bg-red-50 text-red-600 text-xs font-semibold transition-colors"
                                        title="Hapus"
                                    >
                                        <i class="pi pi-trash text-[11px]" />
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Empty State -->
                        <tr v-if="!documents.data || documents.data.length === 0">
                            <td colspan="4" class="py-12 text-center text-slate-400">
                                <i class="pi pi-inbox text-3xl mb-2 block text-slate-300" />
                                <span class="font-medium text-xs">Tidak ada data berkas yang sesuai dengan filter saat ini.</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div
                v-if="documents.total > 0"
                class="px-5 py-3.5 border-t border-slate-200 bg-white flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-600"
            >
                <div>
                    Menampilkan <span class="font-bold font-mono">{{ documents.from || 0 }}</span> sampai
                    <span class="font-bold font-mono">{{ documents.to || 0 }}</span> dari
                    <span class="font-bold font-mono">{{ documents.total?.toLocaleString('id-ID') }}</span> data agama
                </div>

                <div class="flex items-center gap-1">
                    <template v-for="(link, lIdx) in documents.links" :key="lIdx">
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

        <!-- ================= MODAL CREATE & EDIT ================= -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
        >
            <div class="relative w-full max-w-md bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
                <!-- Header -->
                <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center gap-2.5">
                        <div class="size-8 rounded-lg bg-[#005BAC]/10 text-[#005BAC] flex items-center justify-center">
                            <i :class="isEdit ? 'pi pi-pencil' : 'pi pi-plus'" class="text-sm" />
                        </div>
                        <h3 class="font-bold text-sm text-slate-900">
                            {{ modalTitle }}
                        </h3>
                    </div>
                    <button
                        type="button"
                        @click="closeModal"
                        class="size-8 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-200/60 flex items-center justify-center transition-colors"
                    >
                        <i class="pi pi-times text-sm" />
                    </button>
                </div>

                <!-- Body -->
                <form @submit.prevent="submitForm" class="p-5 space-y-4">
                    <!-- Nama -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">
                            Nama Berkas <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="form.name"
                            type="text"
                            placeholder="Contoh: KTP, KK, Ijazah, dll."
                            class="w-full px-3.5 py-2.5 rounded-xl border text-sm focus:outline-none focus:ring-2 focus:ring-[#005BAC]/30 focus:border-[#005BAC]"
                            :class="form.errors.name ? 'border-red-400' : 'border-slate-300'"
                        />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <!-- Footer -->
                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button
                            type="button"
                            @click="closeModal"
                            class="px-4 py-2 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 font-semibold text-xs transition-colors"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#005BAC] hover:bg-[#004785] text-white font-semibold text-xs transition-colors disabled:opacity-60"
                        >
                            <i v-if="form.processing" class="pi pi-spin pi-spinner text-xs" />
                            <span>{{ isEdit ? 'Update Data' : 'Simpan Data' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</template>