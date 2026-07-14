<template>
    <div class="container-fluid">
        <div class="row my-3">
            <div class="col-12">
                <div class="card card-body">
                <div v-if="alertMsg" class="alert alert-danger alert-dismissible fade show py-2" role="alert">
                    {{ alertMsg }}
                    <button type="button" class="btn-close py-2" @click="alertMsg = ''"></button>
                </div>
                <Form
                    ref="formRef"
                    :statuses="statuses"
                    :errors="formErrors"
                    :edit-repair="editRepair"
                    @reset-form="handleReset"
                    @update-repair="handleUpdateRepair"
                />
            </div>
            </div>
        </div>
    </div>
</template>

<script setup>
    import { computed, onMounted, reactive, ref } from "vue";
    import { useRoute, useRouter } from "vue-router";
    import { useRepair } from "../../stores/repair";
    import Form from "./Form.vue";

    const dash = useRepair();
    const router = useRouter();
    const route = useRoute();

    const statuses = computed(() => dash.allStatuses);

    const formRef = ref(null);
    const editRepairData = ref(null);
    const alertMsg = ref('');

    const editRepair = computed(() => editRepairData.value);

    const formErrors = reactive({
        product: '',
        damage: '',
        status: '',
        unit: '',
        branch: '',
        repair_shop: '',
        qty: '',
        repair_cost: '',
        remarks: '',
    });

    async function load() {
        if (route.params.id) {
            const loadPromises = [
                dash.loadRepairs({}, 1, 20),
                dash.getRepair(route.params.id)
            ];
            const results = await Promise.all(loadPromises);
            const repairResult = results[1];
            if (repairResult.status === 'success') {
                editRepairData.value = repairResult.data.repair;
            }
        } else {
            router.push({ name: 'repairs' });
        }
    }

    onMounted(() => {
        load();
    });

    onMounted(() => {
        if (window.Echo) {
            window.Echo.channel('repairs')
                .listen('.repair-updated', () => {
                    load();
                });
        }
    });

    const handleUpdateRepair = async (formData) => {
        Object.keys(formErrors).forEach(key => formErrors[key] = '');
        const result = await dash.updateRepair(route.params.id, formData);

        if (result.status === 'error' && result.errors) {
            let msgs = [];
            for (const key in result.errors) {
                if (formErrors[key] !== undefined) {
                    formErrors[key] = result.errors[key];
                    const m = Array.isArray(result.errors[key]) ? result.errors[key][0] : result.errors[key];
                    if (m && !m.toLowerCase().includes('required') && !m.toLowerCase().includes('numeric') && !m.toLowerCase().includes('greater than')) msgs.push(m);
                }
            }
            alertMsg.value = msgs.join(', ');
        }

        if (result.status === 'success') {
            editRepairData.value = result.data.repair || editRepairData.value;
            alert('Repair updated successfully');
        }
        return result;
    };

    const handleReset = () => {
        if (formRef.value && typeof formRef.value.resetForm === 'function') {
            formRef.value.resetForm();
        }
        Object.keys(formErrors).forEach(key => formErrors[key] = '');
        alertMsg.value = '';
    };
</script>
