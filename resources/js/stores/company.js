import axios from "axios";
import { defineStore } from "pinia";

export const useCompany = defineStore("dashCompany", {
    state: () => ({
        company: null,
        loading: false,
        errors: null,
        message: ""
    }),

    getters: {
        companyData: (state) => state.company,
    },

    actions: {
        async loadCompany() {
            this.loading = true;
            const result = await axios("/api/companies", { params: { perPage: 1 } })
            .then((res) => {
                const companies = res.data.companies?.data || [];
                this.company = companies.length ? companies[0] : null;
            })
            .catch((errors) => {
                console.log(errors);
            })
            .finally(() => {
                this.loading = false;
            });

            return result;
        },

        async saveCompany(companyData) {
            this.loading = true;
            let result;

            if (this.company) {
                result = await axios.post(`/api/companies/update/${this.company.id}`, companyData)
                .then((res) => {
                    this.company = res.data.company;
                    return { status: 'success', message: res.data.message };
                })
                .catch ((error) => {
                    if (error.response?.status === 422) {
                        return { status: 'error', errors: error.response.data.errors };
                    }
                    return { status: 'error', message: error.message };
                });
            } else {
                result = await axios.post("/api/companies", companyData)
                .then((res) => {
                    this.company = res.data.company;
                    return { status: 'success', message: res.data.message };
                })
                .catch ((error) => {
                    if (error.response?.status === 422) {
                        return { status: 'error', errors: error.response.data.errors };
                    }
                    return { status: 'error', message: error.message };
                });
            }

            this.loading = false;
            return result;
        },
    }
});
