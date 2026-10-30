<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Card from 'primevue/card';
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import { ref, computed } from 'vue';

defineOptions({ layout: AuthenticatedLayout });

const search = ref('');
const statusFilter = ref(null);
const statusOptions = ref([
    { label: 'All', value: null },
    { label: 'Active', value: 'Active' },
    { label: 'Inactive', value: 'Inactive' },
    { label: 'Pending', value: 'Pending' },
]);

const users = ref([
    { id: 1,  name: 'Alice Johnson',   email: 'alice@example.com',   role: 'Admin',    status: 'Active',   joined: '2024-01-15' },
    { id: 2,  name: 'Bob Smith',        email: 'bob@example.com',      role: 'User',     status: 'Active',   joined: '2024-02-20' },
    { id: 3,  name: 'Carol White',      email: 'carol@example.com',    role: 'Editor',   status: 'Inactive', joined: '2024-03-10' },
    { id: 4,  name: 'David Brown',      email: 'david@example.com',    role: 'User',     status: 'Pending',  joined: '2024-04-05' },
    { id: 5,  name: 'Eva Martinez',     email: 'eva@example.com',      role: 'Manager',  status: 'Active',   joined: '2024-04-18' },
    { id: 6,  name: 'Frank Lee',        email: 'frank@example.com',    role: 'User',     status: 'Active',   joined: '2024-05-01' },
    { id: 7,  name: 'Grace Kim',        email: 'grace@example.com',    role: 'Editor',   status: 'Active',   joined: '2024-05-14' },
    { id: 8,  name: 'Henry Wilson',     email: 'henry@example.com',    role: 'User',     status: 'Inactive', joined: '2024-06-03' },
]);

const filteredUsers = computed(() => {
    return users.value.filter((u) => {
        const matchSearch = search.value === '' ||
            u.name.toLowerCase().includes(search.value.toLowerCase()) ||
            u.email.toLowerCase().includes(search.value.toLowerCase());
        const matchStatus = statusFilter.value === null || u.status === statusFilter.value;
        return matchSearch && matchStatus;
    });
});

const statusSeverity = (s) => ({ Active: 'success', Inactive: 'danger', Pending: 'warn' }[s] ?? 'secondary');
const roleSeverity   = (r) => ({ Admin: 'contrast', Manager: 'info', Editor: 'secondary' }[r] ?? null);

const selectedUsers = ref([]);
</script>

<template>
    <div class="space-y-6">

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-surface-900">Data Table</h1>
                <p class="text-sm text-surface-500 mt-0.5">User management with filtering &amp; selection</p>
            </div>
            <div class="flex gap-2">
                <Button label="Export" icon="pi pi-download" severity="secondary" outlined size="small" />
                <Button label="Add User" icon="pi pi-plus" size="small" />
            </div>
        </div>

        <Card class="shadow-sm">
            <!-- Toolbar -->
            <template #header>
                <div class="flex flex-wrap items-center justify-between gap-3 px-6 pt-5 pb-4">
                    <div class="flex gap-3 flex-wrap">
                        <InputText
                            v-model="search"
                            placeholder="Search users..."
                            class="w-56"
                        >
                            <template #prefix><i class="pi pi-search text-surface-400" /></template>
                        </InputText>
                        <Select
                            v-model="statusFilter"
                            :options="statusOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Filter status"
                            class="w-40"
                        />
                    </div>
                    <span v-if="selectedUsers.length" class="text-sm text-surface-600">
                        {{ selectedUsers.length }} row(s) selected
                        <Button label="Delete selected" text size="small" severity="danger" class="ml-2" />
                    </span>
                </div>
            </template>

            <template #content>
                <DataTable
                    v-model:selection="selectedUsers"
                    :value="filteredUsers"
                    dataKey="id"
                    paginator
                    :rows="5"
                    :rowsPerPageOptions="[5, 10, 20]"
                    sortMode="multiple"
                    removableSort
                    stripedRows
                    tableStyle="min-width: 50rem"
                >
                    <Column selectionMode="multiple" headerStyle="width: 3rem" />
                    <Column field="id"     header="ID"     sortable style="width: 4rem" />
                    <Column field="name"   header="Name"   sortable />
                    <Column field="email"  header="Email"  sortable />
                    <Column field="role"   header="Role"   sortable>
                        <template #body="{ data }">
                            <Tag :value="data.role" :severity="roleSeverity(data.role)" />
                        </template>
                    </Column>
                    <Column field="status" header="Status" sortable>
                        <template #body="{ data }">
                            <Tag :value="data.status" :severity="statusSeverity(data.status)" rounded />
                        </template>
                    </Column>
                    <Column field="joined" header="Joined"  sortable />
                    <Column header="Actions">
                        <template #body>
                            <div class="flex gap-1">
                                <Button icon="pi pi-eye"    text rounded size="small" severity="info" />
                                <Button icon="pi pi-pencil" text rounded size="small" severity="warn" />
                                <Button icon="pi pi-trash"  text rounded size="small" severity="danger" />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </template>
        </Card>

    </div>
</template>

