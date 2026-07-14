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
                        :products="products"
                        :branches="branches"
                        :units="units"
                        :errors="formErrors"
                        :edit-damage="editDamage"
                        @reset-form="handleReset"
                        @update-damage="handleUpdateDamage"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
    import { computed, onMounted, reactive, ref } from "vue";
    import { useRoute, useRouter } from "vue-router";
    import { useDamage } from "../../stores/damage";
    import Form from "./Form.vue";

    const dash = useDamage();
    const router = useRouter();
    const route = useRoute();

    const statuses = computed(() => dash.allStatuses);
    const products = computed(() => dash.allProducts);
    const branches = computed(() => dash.allBranches);
    const units = computed(() => dash.allUnits);

    const formRef = ref(null);
    const editDamageData = ref(null);
    const alertMsg = ref('');

    const editDamage = computed(() => editDamageData.value || dash.allEditDamage);

    const formErrors = reactive({
        product: '',
        status: '',
        unit: '',
        branch: '',
        qty: '',
        remarks: '',
    });

    async function load() {
        await dash.loadDamages({}, 1, 20);
        if (route.params.id) {
            const result = await dash.getDamage(route.params.id);
            if (result.status === 'success') {
                editDamageData.value = result.data.damage;
            }
        }
    }

    onMounted(() => {
        load();
    });

    onMounted(() => {
        if (window.Echo) {
            window.Echo.channel('damages')
                .listen('.damage-updated', () => {
                    load();
                });
        }
    });

    const handleUpdateDamage = async (formData) => {
        Object.keys(formErrors).forEach(key => formErrors[key] = '');
        alertMsg.value = '';
        let result;
        if (editDamage.value) {
            result = await dash.updateDamage(editDamage.value.id, formData);
        } else {
            result = await dash.createDamage(formData);
        }

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
            handleReset();
        }
        return result;
    };

    const handleReset = () => {
        if (formRef.value && typeof formRef.value.resetForm === 'function') {
            formRef.value.resetForm();
        }
        Object.keys(formErrors).forEach(key => formErrors[key] = '');
        editDamageData.value = null;
        if (route.params.id) {
            router.push({ name: 'damage-create' });
        }
    };
</script>

<style lang="scss" scoped>
</style>
