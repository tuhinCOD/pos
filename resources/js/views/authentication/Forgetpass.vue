<template>
    <nav class="navbar navbar-expand-lg navbar-light navbar-laravel">
        <div class="container">
            <a class="navbar-brand" href="#">Password Forget Form</a>
        </div>
    </nav>

    <main class="login-form">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">Reset Your Password</div>
                        <div class="card-body">
                            <form id="newForgetForm" @submit.prevent="submitForm">
                                <div class="form-group row">
                                    <input type="hidden" name="email" v-model="email">
                                    <label for="password" class="col-md-4 col-form-label text-md-right">New Password</label>
                                    <div class="col-md-6">
                                        <input type="password" v-model="password" id="password" class="form-control" name="password" placeholder="New Password" required autofocus>
                                    </div>

                                    <label for="password_confirmation" class="col-md-4 col-form-label text-md-right mt-3">Re-type Password</label>
                                    <div class="col-md-6">
                                        <input type="password" v-model="password_confirmation" id="password_confirmation" class="form-control mt-3" name="password_confirmation" placeholder="Confirm Password" required autofocus>
                                    </div>
                                </div>

                                <div class="col-md-6 offset-md-4 mt-3">
                                    <button type="submit" id="resetBtn" class="btn btn-primary" :disabled="auth.loading">{{auth.loading ? "Processing..." : "Reset"}}</button>
                                </div>
                                <div id="ajaxError" class="mt-2 text-danger">{{ auth.message }}</div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</template>

<script setup>
import { ref, onMounted, nextTick } from "vue";
import { useRouter } from "vue-router";
import { useAuth } from "@/stores/auth";

const auth = useAuth();
const router = useRouter();
const email = ref(localStorage.getItem("forget_email"));
const password = ref("");
const password_confirmation = ref("");

const submitForm = async () => {
  const result = await auth.forgetPassword({
    email: email.value, 
    password: password.value,
    password_confirmation: password_confirmation.value
  });

  if (result) {
    setTimeout(() => {
        router.push({path: "/login"});
    }, 1500);
  }
};

onMounted(() => { 
    auth.errors = {};
    auth.message = "";
    focus ? nextTick(() => document.querySelector(`#password`).focus()) : false 
});
</script>


<style lang="scss" scoped>

</style>