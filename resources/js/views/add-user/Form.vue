<template>
    <form id="user-form" @submit.prevent="updateForm">
        <div class="d-flex justify-content-between align-items-center">
            <h3 class="mb-0">
                {{ isEditPage ? 'Edit User' : 'Add User' }}
            </h3>
                <button
                    class="btn btn-primary btn-sm"
                    @click="goToViewUser"
                    type="button"
                >
                    <i class="bi bi-eye"></i>
                    View users
                </button>
        </div>
        <div class="my-3">
            <div class="row g-4">
                <div class="col-3">
                    <Input label="Name" id="name" type="text" errid="nameHelp" name="name" :err="errors.name?.[0]" v-model="field.name" placeholder="Full Name" must/>
                </div>
                <div class="col-3">
                    <Input label="Contact" id="contact" type="text" errid="contactHelp" name="contact" :err="errors.contact?.[0]" v-model="field.contact" placeholder="Contact" must/>
                </div>
                <div class="col-3">
                    <Input label="Email" id="email" type="email" errid="emailHelp" name="email" :err="errors.email?.[0]" v-model="field.email" placeholder="Email"/>
                </div>
                <div class="col-3">
                    <Input :label="isEditPage ? 'Password (leave empty to keep)' : 'Password'" id="password" type="password" errid="passwordHelp" name="password" :err="errors.password?.[0]" v-model="field.password" :must="!isEditPage" placeholder="Password"/>
                </div>
                <div class="col-3" style="margin-top: 30px;">
                    <label for="role" class="form-label">
                        Role <span class="text-danger">**</span>
                    </label>
                    <select id="role" class="form-select" aria-label="Role" name="role" v-model="field.role">
                        <option value="">Select Role</option>
                        <option v-for="r in props.roles" :key="r.id" :value="r.id">{{ r.name }}</option>
                    </select>
                    <div id="roleHelp" class="form-text text-danger fw-bold" style="font-size: 10px;">{{ errors.role?.[0] }}</div>
                </div>
                <div class="col-3" style="margin-top: 30px;">
                    <label for="branch" class="form-label">Branch</label>
                    <select id="branch" class="form-select" aria-label="Branch" name="branch" v-model="field.branch">
                        <option value="">Select Branch</option>
                        <option v-for="b in props.branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                    <div id="branchHelp" class="form-text text-danger fw-bold" style="font-size: 10px;">{{ errors.branch?.[0] }}</div>
                </div>
                <div class="col-3" style="margin-top: 30px;">
                    <label for="city" class="form-label">City</label>
                    <select id="city" class="form-select" aria-label="City" name="city" v-model="field.city">
                        <option value="">Select City</option>
                        <option v-for="c in props.cities" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <div id="cityHelp" class="form-text text-danger fw-bold" style="font-size: 10px;">{{ errors.city?.[0] }}</div>
                </div>
                <div class="col-3" style="margin-top: 30px;">
                    <label for="status" class="form-label">Status</label>
                    <select id="status" class="form-select" aria-label="Default select example" name="status" v-model="field.status">
                        <option value="">Select Status</option>
                        <option v-for="s in props.statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <div id="statusHelp" class="form-text text-danger fw-bold" style="font-size: 10px;">{{ errors.status?.[0] }}</div>
                </div>
                <div class="col-3">
                    <Input label="NID" id="nid" type="text" errid="nidHelp" name="nid" :err="errors.nid?.[0]" v-model="field.nid" placeholder="National ID"/>
                </div>
                <div class="col-4">
                    <label for="address" class="form-label">Address</label>
                    <textarea name="address" class="form-control" id="address" placeholder="Write address here..." v-model="field.address"></textarea>
                    <div id="addressHelp" class="form-text text-danger fw-bold" style="font-size: 10px;">{{ errors.address?.[0] }}</div>
                </div>
                <div class="col-4">
                    <label for="remarks" class="form-label">Remarks</label>
                    <textarea id="remarks" class="form-control" name="remarks" v-model="field.remarks" placeholder="Remarks"></textarea>
                    <div id="remarksHelp" class="form-text text-danger fw-bold" style="font-size: 10px;">{{ errors.remarks?.[0] }}</div>
                </div>
            </div>
            <div class="col-12 mt-3">
                <div class="form-text text-danger">** At first select a role</div>
                <div id="errorDiv" class="form-text text-danger"></div>
            </div>
        </div>
        <div class="row g-2 my-3">
            <div class="col-6">
                <Button :label="props.editUser ? 'Update' : 'Submit'" id="submitBtn" type="submit" icon="bi bi-save-fill"/>
            </div>
            <div class="col-6">
                <Button label="Reset" type="button" id="resetBtn" color="secondary" icon="bi bi-x-square-fill" @click="onReset"/>
            </div>
        </div>
    </form>
</template>

<script setup>
    import Input from '../../components/Input.vue';
    import Button from '../../components/Button.vue';
    import { reactive, ref, computed, watch } from 'vue';
    import { useRoute, useRouter } from "vue-router";

    const route = useRoute();
    const router = useRouter();

    const goToViewUser = () => {
        router.push({ name: 'users' });
    };

    const isEditPage = computed(() => {
        return route.name === 'edit-user';
    });

    const props = defineProps({
        branches: { type: Array, default: () => [] },
        roles: { type: Array, default: () => [] },
        cities: { type: Array, default: () => [] },
        statuses: { type: Array, default: () => [] },
        errors: { type: Object, default: () => ({}) },
        editUser: { type: Object, default: null },
    });

    const emit = defineEmits([
        "reset-form",
        "update-user",
    ]);

    const field = reactive({
        name: '',
        contact: '',
        email: '',
        password: '',
        role: '',
        branch: '',
        city: '',
        status: '',
        nid: '',
        address: '',
        remarks: '',
    });

    const isEditing = ref(false);

    const updateForm = () => {
        const formData = new FormData();

        for (const key in field) {
            formData.append(key, field[key]);
        }

        emit('update-user', formData);
    };

    watch(() => props.editUser, (val) => {
        if (val) {
            isEditing.value = true;
            field.name = val.name || '';
            field.contact = val.contact || '';
            field.email = val.email || '';
            field.password = '';
            field.role = val.role_id || val.role?.id || '';
            field.branch = val.branch_id || val.branch?.id || '';
            field.city = val.city_id || val.city?.id || '';
            field.status = val.status_id || val.status?.id || '';
            field.nid = val.nid || '';
            field.address = val.address || '';
            field.remarks = val.remarks || '';
        } else {
            isEditing.value = false;
        }
    }, { immediate: true });

    const resetForm = () => {
        field.name = '';
        field.contact = '';
        field.email = '';
        field.password = '';
        field.role = '';
        field.branch = '';
        field.city = '';
        field.status = '';
        field.nid = '';
        field.address = '';
        field.remarks = '';
        isEditing.value = false;
    };

    const onReset = () => {
        resetForm();
        emit("reset-form");
    };

    defineExpose({
        resetForm,
        field,
    });
</script>

<style lang="scss" scoped>

</style>
