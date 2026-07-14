<template>
    <nav class="navbar navbar-expand-lg navbar-light navbar-laravel">
        <div class="container">
            <a class="navbar-brand" href="#">User Password Recover</a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

        </div>
    </nav>

    <main class="login-form">
        <div class="cotainer">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">Password Recover</div>
                        <div class="card-body">
                            <form id="forgetForm" @submit.prevent="submitForm">
                                <div class="form-group row">
                                    <label for="email_address" class="col-md-4 col-form-label text-md-right">E-Mail Address</label>
                                    <div class="col-md-6">
                                        <input type="text" v-model="email" id="email_address" class="form-control" name="email" required>
                                        <div class="error email-error mt-2 text-danger"></div>
                                        <p class="mt-2">I have an account <router-link to="/login"><b>click here</b></router-link></p>
                                    </div>
                                </div>

                                <div class="col-md-6 offset-md-4">
                                    <button type="submit" class="btn btn-primary" :disabled="auth.loading">
                                        {{auth.loading ? "Processing..." : "Recover"}}
                                    </button>
                                </div>
                                <div id="ajaxError" class="mt-2 text-danger">{{ auth.errors || auth.message }}</div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</template>

<script setup>
    import { ref, onMounted, nextTick } from 'vue';
    import { useRouter } from 'vue-router';
    import { useAuth } from "@/stores/auth";

    const auth = useAuth();
    const router = useRouter();
    const email = ref("");

    const submitForm = async () => {
        await auth.forget(email.value);
    };

    onMounted(() => { 
        auth.errors = "";
        auth.message = "";
        focus ? nextTick(() => document.querySelector(`#email_address`).focus()) : false 
    });
</script>

<style lang="scss" scoped>

</style>