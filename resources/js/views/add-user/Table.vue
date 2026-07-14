<template>
    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr class="text-center">
                    <th class="col-1">id</th>
                    <th class="col-1">name</th>
                    <th class="col-1">contact</th>
                    <th class="col-1">email</th>
                    <th class="col-1">role</th>
                    <th class="col-1">branch</th>
                    <th class="col-1">city</th>
                    <th class="col-1">status</th>
                    <th class="col-1">action</th>
                </tr>
            </thead>
            <tbody id="list">
                <tr v-if="!users || users.length === 0">
                    <td colspan="9" class="text-center text-muted">
                        No data available.
                    </td>
                </tr>
                <tr v-for="user in users" :key="user.id" class="text-center align-middle" style="font-size: 12px">
                    <td>{{ user.id }}</td>
                    <td>{{ user.name }}</td>
                    <td>{{ user.contact }}</td>
                    <td>{{ user.email }}</td>
                    <td>{{ user.role?.name }}</td>
                    <td>{{ user.branch?.name }}</td>
                    <td>{{ user.city?.name }}</td>
                    <td>{{ user.status?.name }}</td>
                    <td>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Basic mixed styles example">
                        <button type="button" class="btn btn-info release-btn" id="release-btn"><i class="bi bi-link-45deg"></i></button>
                        <button type="button" class="btn btn-warning edit-btn" @click="emit('edit-user', user)"><i class="bi bi-pencil-square" ></i></button>
                        <button class="btn btn-danger delete-btn" @click="emit('delete-user', user)"><i class="bi bi-trash"></i></button>
                    </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script setup>
defineProps({
    users: { type: Array, default: () => [] },
});

const emit = defineEmits(['edit-user', 'delete-user']);
</script>

<style lang="scss" scoped>

</style>
