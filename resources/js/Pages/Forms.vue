<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Card from 'primevue/card';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Textarea from 'primevue/textarea';
import Select from 'primevue/select';
import MultiSelect from 'primevue/multiselect';
import DatePicker from 'primevue/datepicker';
import FileUpload from 'primevue/fileupload';
import Checkbox from 'primevue/checkbox';
import Divider from 'primevue/divider';
import FloatLabel from 'primevue/floatlabel';
import IftaLabel from 'primevue/iftalabel';
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

defineOptions({ layout: AuthenticatedLayout });

const form = useForm({
    name: '',
    email: '',
    password: '',
    role: null,
    skills: [],
    bio: '',
    birthdate: null,
    agree: false,
});

const roles = ref([
    { label: 'Administrator', value: 'admin' },
    { label: 'Manager',       value: 'manager' },
    { label: 'Editor',        value: 'editor' },
    { label: 'Viewer',        value: 'viewer' },
]);

const skills = ref([
    { label: 'Laravel',    value: 'laravel' },
    { label: 'Vue 3',      value: 'vue' },
    { label: 'Inertia.js', value: 'inertia' },
    { label: 'Tailwind',   value: 'tailwind' },
    { label: 'PrimeVue',   value: 'primevue' },
    { label: 'TypeScript', value: 'typescript' },
]);

const submit = () => {
    console.log('Form submitted', form.data());
};
</script>

<template>
    <div class="space-y-6">

        <div>
            <h1 class="text-2xl font-bold text-surface-900">Forms</h1>
            <p class="text-sm text-surface-500 mt-0.5">PrimeVue form components with Inertia useForm</p>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

            <!-- Main Form -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Basic Info -->
                <Card class="shadow-sm">
                    <template #title>Basic Information</template>
                    <template #content>
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">

                            <!-- Float label -->
                            <FloatLabel class="w-full">
                                <InputText id="name" v-model="form.name" class="w-full" :invalid="!!form.errors.name" />
                                <label for="name">Full Name</label>
                                <small v-if="form.errors.name" class="text-red-500 mt-1">{{ form.errors.name }}</small>
                            </FloatLabel>

                            <FloatLabel class="w-full">
                                <InputText id="email" v-model="form.email" type="email" class="w-full" :invalid="!!form.errors.email" />
                                <label for="email">Email Address</label>
                                <small v-if="form.errors.email" class="text-red-500 mt-1">{{ form.errors.email }}</small>
                            </FloatLabel>

                            <!-- IFTA label (on-border style) -->
                            <IftaLabel class="w-full">
                                <Select
                                    id="role"
                                    v-model="form.role"
                                    :options="roles"
                                    optionLabel="label"
                                    optionValue="value"
                                    class="w-full"
                                />
                                <label for="role">Role</label>
                            </IftaLabel>

                            <IftaLabel class="w-full">
                                <DatePicker
                                    id="birthdate"
                                    v-model="form.birthdate"
                                    class="w-full"
                                    dateFormat="dd/mm/yy"
                                    showIcon
                                />
                                <label for="birthdate">Birth Date</label>
                            </IftaLabel>

                            <div class="sm:col-span-2">
                                <IftaLabel class="w-full">
                                    <MultiSelect
                                        id="skills"
                                        v-model="form.skills"
                                        :options="skills"
                                        optionLabel="label"
                                        optionValue="value"
                                        display="chip"
                                        class="w-full"
                                        placeholder=" "
                                    />
                                    <label for="skills">Skills</label>
                                </IftaLabel>
                            </div>

                            <div class="sm:col-span-2">
                                <IftaLabel class="w-full">
                                    <Textarea
                                        id="bio"
                                        v-model="form.bio"
                                        rows="4"
                                        class="w-full"
                                    />
                                    <label for="bio">Bio</label>
                                </IftaLabel>
                            </div>
                        </div>
                    </template>
                </Card>

                <!-- Password -->
                <Card class="shadow-sm">
                    <template #title>Security</template>
                    <template #content>
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <FloatLabel class="w-full">
                                <Password id="password" v-model="form.password" class="w-full" toggleMask />
                                <label for="password">Password</label>
                            </FloatLabel>
                            <FloatLabel class="w-full">
                                <Password id="confirm" class="w-full" toggleMask :feedback="false" />
                                <label for="confirm">Confirm Password</label>
                            </FloatLabel>
                        </div>
                    </template>
                </Card>

                <!-- File Upload -->
                <Card class="shadow-sm">
                    <template #title>Avatar</template>
                    <template #content>
                        <FileUpload
                            mode="basic"
                            name="avatar"
                            accept="image/*"
                            :maxFileSize="2000000"
                            chooseLabel="Upload Photo"
                            class="w-full"
                        />
                    </template>
                </Card>

                <!-- Submit -->
                <Card class="shadow-sm">
                    <template #content>
                        <div class="flex items-center gap-3 mb-5">
                            <Checkbox v-model="form.agree" inputId="agree" binary />
                            <label for="agree" class="text-sm text-surface-700">
                                I agree to the <a href="#" class="text-primary underline">Terms of Service</a> and
                                <a href="#" class="text-primary underline">Privacy Policy</a>
                            </label>
                        </div>
                        <div class="flex gap-3">
                            <Button
                                label="Save User"
                                icon="pi pi-check"
                                :disabled="!form.agree || form.processing"
                                :loading="form.processing"
                                @click="submit"
                            />
                            <Button label="Cancel" icon="pi pi-times" severity="secondary" outlined />
                            <Button label="Reset" icon="pi pi-refresh" text severity="secondary" @click="form.reset()" />
                        </div>
                    </template>
                </Card>
            </div>

            <!-- Right Sidebar hints -->
            <div class="space-y-4">
                <Card class="shadow-sm">
                    <template #title>Tips</template>
                    <template #content>
                        <ul class="space-y-3 text-sm text-surface-600">
                            <li class="flex gap-2"><i class="pi pi-info-circle text-primary mt-0.5" /> <span>Use <code class="bg-surface-100 px-1 rounded text-xs">useForm()</code> from Inertia for form state &amp; errors.</span></li>
                            <li class="flex gap-2"><i class="pi pi-info-circle text-primary mt-0.5" /> <span><code class="bg-surface-100 px-1 rounded text-xs">FloatLabel</code> animates the label above the input on focus.</span></li>
                            <li class="flex gap-2"><i class="pi pi-info-circle text-primary mt-0.5" /> <span><code class="bg-surface-100 px-1 rounded text-xs">IftaLabel</code> keeps the label always visible inside the border.</span></li>
                            <li class="flex gap-2"><i class="pi pi-info-circle text-primary mt-0.5" /> <span>Bind <code class="bg-surface-100 px-1 rounded text-xs">:invalid</code> prop to show PrimeVue's error state styling.</span></li>
                        </ul>
                    </template>
                </Card>

                <Card class="shadow-sm">
                    <template #title>Form State</template>
                    <template #content>
                        <pre class="text-xs bg-surface-100 rounded p-3 overflow-auto max-h-48">{{ JSON.stringify(form.data(), null, 2) }}</pre>
                    </template>
                </Card>
            </div>
        </div>
    </div>
</template>

