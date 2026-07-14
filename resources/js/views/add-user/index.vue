<template>
    <div class="container-fluid">
        <div class="my-3">
            <div class="card card-body">
                <Form
                ref="formRef"
                :branches="branches"
                :roles="roles"
                :cities="cities"
                :statuses="statuses"
                :errors="formErrors"
                :editUser="editUser"
                @reset-form="handleReset"
                @update-user="handleUpdateUser"
                />
            </div>
        </div>
        <div class="my-3" v-if="editUser">
            <div class="card card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted">Editing user: <strong>{{ editUser.name }}</strong></span>
                    <div>
                        <button class="btn btn-danger me-2" @click="handleDelete">
                            <i class="bi bi-trash"></i> Delete User
                        </button>
                        <button class="btn btn-secondary" @click="cancelEdit">
                            <i class="bi bi-x-circle"></i> Cancel Edit
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <!-- <div class="my-3">
            <div class="card card-body">
                <Table :users="users" @edit-user="handleEditUser" @delete-user="handleDeleteFromTable"/>
            </div>
        </div> -->
    </div>
</template>

<script setup>
    import { computed, onMounted, reactive, ref, watch } from "vue";
    import { useRoute, useRouter } from "vue-router";
    import { useUser } from "../../stores/user";
    import Form from "./Form.vue";
    import Table from "./Table.vue";

    const dash = useUser();
    const route = useRoute();
    const router = useRouter();

    const branches = computed(() => dash.allBranches);
    const roles = computed(() => dash.allRoles);
    const cities = computed(() => dash.allCities);
    const statuses = computed(() => dash.allStatuses);
    const users = computed(() => dash.allUsers);

    const formRef = ref(null);
    const editUser = ref(null);

    const formErrors = reactive({
        name: '',
        contact: '',
        email: '',
        password: '',
        address: '',
        city: '',
        nid: '',
        branch: '',
        status: '',
        role: '',
        remarks: '',
    });

    const handleUpdateUser = async (userData) => {
        let result;

       if (!userData.get('role')) {
            alert('Please choose a role first');
            return;
        }

        if (editUser.value) {
            result = await dash.updateUser(editUser.value.id, userData);
        } else {
            result = await dash.createUser(userData);
        }

        Object.keys(formErrors).forEach(key => formErrors[key] = '');

        if (result.status === 'error' && result.errors) {
            for (const key in result.errors) {
                if (formErrors[key] !== undefined) {
                    formErrors[key] = result.errors[key];
                }
            }
        }

        if (result.status === 'success') {
            handleReset();
        }

        return result;
    };

    const handleEditUser = (user) => {
        editUser.value = user;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const handleDelete = async () => {
        if (!editUser.value) return;
        if (!confirm(`Are you sure you want to delete user "${editUser.value.name}"?`)) return;

        const result = await dash.deleteUser(editUser.value.id);
        if (result.status === 'success') {
            handleReset();
        } else {
            alert('Failed to delete user');
        }
    };

    const handleDeleteFromTable = async (product) => {
        if (!confirm(`Are you sure you want to delete client "${user.name}"?`)) return;
        await dash.deleteUser(user.id);
    };

    const handleReset = () => {
        if (formRef.value && typeof formRef.value.resetForm === 'function') {
            formRef.value.resetForm();
        }
        Object.keys(formErrors).forEach(key => formErrors[key] = '');
        editUser.value = null;
        router.push({ name: 'add-user' });
    };

    const cancelEdit = () => {
        handleReset();
    };

    onMounted(async () => {
        if (!dash.allUsers.length) {
            await dash.loadUsers();
        }

        const id = route.params.id;
        if (id) {
            const found = dash.allUsers.find(u => u.id === Number(id));
            if (found) {
                editUser.value = found;
            }
        }
    });

    onMounted(() => {
        if (window.Echo) {
            window.Echo.channel('users')
                .listen('.user-updated', () => {
                    if (!dash.allUsers.length) {
                        dash.loadUsers();
                    }
                });
        }
    });

    watch(() => route.params.id, (id) => {
        if (!id) {
            handleReset();
        } else {
            const found = dash.allUsers.find(u => u.id === Number(id));
            if (found) {
                editUser.value = found;
            }
        }
    });
</script>

<style lang="scss" scoped>

</style>
