<template>
    <div class="container">
        <div class="row my-3">
            <div class="col-8 mx-auto">
                <div class="card card-body">
                    <form id="company-form" @submit.prevent="handleSave">
                        <h3>Company Settings</h3>
                        <div class="my-3">
                            <div class="row g-2">
                                <div class="col-6">
                                    <Input label="Company Name" id="name" type="text" errid="nameHelp" name="name" v-model="field.name" placeholder="Company Name" :err="errors.name?.[0]" must/>
                                </div>
                                <div class="col-6">
                                    <Input label="Contact" id="contact" type="text" errid="contactHelp" name="contact" v-model="field.contact" placeholder="Contact" :err="errors.contact?.[0]" must/>
                                </div>
                                <div class="col-6">
                                    <Input label="Email" id="email" type="email" errid="emailHelp" name="email" v-model="field.email" placeholder="Email" :err="errors.email?.[0]" must/>
                                </div>
                                <div class="col-6">
                                    <Input label="Website" id="website" type="text" errid="websiteHelp" name="website" v-model="field.website" placeholder="Website" :err="errors.website?.[0]"/>
                                </div>
                                <div class="col-12">
                                    <Input label="Address" id="address" type="text" errid="addressHelp" name="address" v-model="field.address" placeholder="Address" :err="errors.address?.[0]" must/>
                                </div>
                                <div class="col-6">
                                    <label for="logo" class="form-label">Logo</label>
                                    <input class="form-control shadow-none" type="file" id="logo" name="logo" @change="onFileChange" accept="image/jpeg,image/png,image/jpg">
                                    <div class="form-text text-danger fw-bold" style="font-size: 10px;">{{ errors.logo?.[0] }}</div>
                                </div>
                                <div class="col-6" v-if="logoPreview || (company && company.logo)">
                                    <div class="mt-2">
                                        <img :src="logoPreview || `/storage/${company.logo}`" alt="Logo" class="img-thumbnail" style="max-height: 80px;">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row g-2 my-3">
                            <div class="col-2">
                                <Button label="Save" id="submitBtn" type="submit"/>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
    import Input from '../../components/Input.vue';
    import Button from '../../components/Button.vue';
    import { onMounted, reactive, ref } from 'vue';
    import { useCompany } from '../../stores/company.js';

    const dash = useCompany();

    const company = ref(null);
    const logoPreview = ref(null);

    const field = reactive({
        name: '',
        contact: '',
        email: '',
        website: '',
        address: '',
    });

    const errors = reactive({
        name: '',
        contact: '',
        email: '',
        website: '',
        address: '',
        logo: '',
    });

    onMounted(async () => {
        await dash.loadCompany();
        company.value = dash.companyData;

        if (company.value) {
            field.name = company.value.name || '';
            field.contact = company.value.contact || '';
            field.email = company.value.email || '';
            field.website = company.value.website || '';
            field.address = company.value.address || '';
        }
    });

    const onFileChange = (e) => {
        const file = e.target.files[0];
        if (file) {
            logoPreview.value = URL.createObjectURL(file);
        }
    };

    const handleSave = async () => {
        const formData = new FormData();

        for (const key in field) {
            formData.append(key, field[key]);
        }

        const logoInput = document.querySelector('#logo');
        if (logoInput && logoInput.files[0]) {
            formData.append('logo', logoInput.files[0]);
        }

        const result = await dash.saveCompany(formData);

        Object.keys(errors).forEach(key => errors[key] = '');

        if (result.status === 'error' && result.errors) {
            for (const key in result.errors) {
                if (errors[key] !== undefined) {
                    errors[key] = result.errors[key];
                }
            }
        }

        if (result.status === 'success') {
            company.value = dash.companyData;
        }
    };

    const onReset = () => {
        if (company.value) {
            field.name = company.value.name || '';
            field.contact = company.value.contact || '';
            field.email = company.value.email || '';
            field.website = company.value.website || '';
            field.address = company.value.address || '';
        } else {
            field.name = '';
            field.contact = '';
            field.email = '';
            field.website = '';
            field.address = '';
        }
        logoPreview.value = null;
        document.querySelector('#logo').value = '';
        Object.keys(errors).forEach(key => errors[key] = '');
    };
</script>

<style lang="scss" scoped>

</style>
