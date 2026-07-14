<template>
    <div class="container-box">    
        <div class="form-box">
            <h1 id="title">Log In</h1>
            <form id="loginForm" @submit.prevent="handleLogin">
                <div id="input-group">
                    <div class="input-field">
                        <i class="bi bi-envelope-fill"></i>
                        <input v-model="email" name="email" type="text" placeholder="Email">
                        <div class="error email-error">{{ auth.errors?.email?.[0] }}</div>
                    </div>
                    <div class="input-field">
                        <i class="bi bi-lock-fill"></i>
                        <input v-model="password" name="password" type="password" placeholder="password">
                        <div class="error password-error">{{ auth.errors?.password?.[0]}}</div>
                    </div>
                    <p><router-link to="/forget">Forget Password?</router-link></p>
                    <p>Create a new account <router-link to="/signup">Click Here!</router-link></p>
                </div>
                <div class="btn-field">
                    <button id="loginBtn" type="submit" :disabled="auth.loading">{{ auth.loading ? "Processing..." : "Login" }}</button>
                </div>
                <div id="ajaxError" style="color:red; text-align:center; margin-top:10px;">{{ auth.message }}</div>
            </form>
        </div>
    </div>
</template>

<script setup>
    import '../../assets/custom/project1.css';
    import { ref, onMounted } from "vue";
    import { useAuth } from "@/stores/auth";

    const auth = useAuth();
    const email = ref("");
    const password = ref("");

    onMounted(async () => {
        auth.errors = {};
        auth.message = "";
    });

    const handleLogin = async () => {
        const success = await auth.login(email.value, password.value);
        if (success) {
            setTimeout(() => {
            window.location.href = "/";
            }, 1200);
        }
    };
</script>

<style lang="scss" scoped>

</style>