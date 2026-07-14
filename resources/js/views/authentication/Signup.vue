<template>
  <div class="container-box">
    <div class="form-box">
      <h1 id="title">Sign Up</h1>

      <form @submit.prevent="submit">
        <div id="input-group">

          <div class="input-field">
            <i class="bi bi-person-fill"></i>
            <input
              type="text"
              placeholder="Name"
              v-model="name"
              name="name"
            />
            <div class="error">
              {{ auth.errors?.name?.[0]}}
            </div>
          </div>

          <div class="input-field">
            <i class="bi bi-lock-fill"></i>
            <input
              type="password"
              placeholder="Password"
              v-model="password"
              name="password"
            />
            <div class="error">
              {{ auth.errors?.password?.[0] }}
            </div>
          </div>

          <div class="input-field">
            <i class="bi bi-envelope-fill"></i>
            <input
              type="email"
              placeholder="Email"
              v-model="email"
              name="email"
            />
            <div class="error">
              {{auth.errors?.email?.[0]}}
            </div>
          </div>

          <p>
            Already have an account?
            <router-link to="/login">Click Here!</router-link>
          </p>
        </div>

        <div class="btn-field">
          <button type="submit" :disabled="auth.loading">
            Sign Up
          </button>
        </div>

        <div
          id="ajaxError"
          :style="{ color: auth.errors ? 'red' : 'green', textAlign: 'center', marginTop: '10px' }"
        >
          {{ auth.loading ? "Processing..." : "" }}
        </div>
      </form>
    </div>
  </div>
</template>


<script setup>
import { ref, onMounted } from "vue";
import { useRouter } from "vue-router";
import { useAuth } from "@/stores/auth";
import "../../assets/custom/project1.css";

const auth = useAuth();
const router = useRouter();

const name = ref("");
const email = ref("");
const password = ref("");

onMounted(() => {
  auth.errors = {};
  auth.message = "";
});

const submit = async () => {
  const ok = await auth.signup(
    name.value,
    email.value,
    password.value
  );

  if (ok) {
    setTimeout(() => {
      router.push({path: "/verification"});
    }, 1500);
  }
};
</script>


<style lang="scss" scoped>

</style>