import axios from "axios";
import { defineStore } from "pinia";

export const useAuth = defineStore("auth", {
  state: () => ({
    user: null,
    loading: false,
    errors: null,
    message: ''
  }),

  actions: {
    async me () {
      try {
        const res = await axios.post('/api/me');
        this.user = res.data;
        return res.data;
      } catch (e) {
        console.log(e.message);
        this.logout();
      }
    },

    async login(email, password) {
      this.loading = true
      this.errors = null
      this.message = ''

      try {
        const res = await axios.post('/api/login', { email, password })
        return true
      } catch (error) {
        this.errors = error.response?.data?.errors || {}
        if (error.response?.status === 401) {
          this.message = error.response?.data?.message || 'Login failed'
        }
        return false
      } finally {
        this.loading = false;
      }
    },

    async logout() {
      this.loading = true

      try {
        const res = await axios.post('/api/logout');
        if (res.data.status === "success") {
          window.location.href = "/login";
        }
      } catch (error) {
        console.warn('Logout API failed, clearing local state')
      } finally {
        this.user = null
        this.loading = false
      }
    },

     async resetPassword(payload) {
      this.loading = true;
      this.errors = {};
      this.message = "";

      try {
        const res = await axios.post(
          "/api/password-change", payload, 
          {
            headers: {
              Accept: "application/json",
              "Content-Type": "application/json",
            },
          }
        );

        if (res.data.status === "success") {
          this.message = "Password reset successful";
          this.logout();
        } else {
          this.message = res.message || "Something went wrong!";
        }
      } catch (err) {
        this.errors = err.response?.data?.errors || {};
        this.message = "Request failed: " + err.response?.data?.message;
        this.loading = false;
      } finally {
        this.loading = false;
      }
    },

    async signup(name, email, password) {
      this.loading = true;
      this.errors = {};
      this.message = "";

      try {
        const res = await axios.post(
          "api/signup", {name, email, password}, 
          {
            headers: {
              Accept: "application/json",
              "Content-Type": "application/json",
            },
          }
        );

        if (res.data.status === "success") {
          this.message = "Signup successful. Redirecting...";
          localStorage.setItem("signup_email", res.data.email);
          return true;
        }

      } catch (err) {
        this.loading = false;
        this.errors = err.response?.data?.errors || {};
        this.message = err.response?.data?.message;
      } finally {
        this.loading = false;
        this.message = '';
      }
    },

    async forget(email) {
      this.loading = true;
      this.errors = "";
      this.message = "";

      try {
        const res = await axios.post(
          "/api/forget-pass", {email: email}, 
          {
            headers: {
              Accept: "application/json",
              "Content-Type": "application/json",
            },
          }
        );

        if (res.data.status === "success") {
          this.message = res.data.message;
          localStorage.setItem("forget_email", res.data.email);
          return true;
        } else {
          this.errors = res.data.errors || "";
          this.message = res.data.message || "Something went wrong!";
          return false;
        }
      } catch (err) {
        this.message = err.response?.data?.message;
        this.loading = false;
        return false;
      } finally {
        this.loading = false;
      }
    },

    async forgetPassword(payload) {
      this.loading = true;
      this.errors = {};
      this.message = "";

      try {
        const res = await axios.post(
          "/api/reset-pass", payload, 
          {
            headers: {
              Accept: "application/json",
              "Content-Type": "application/json",
            }
          }
        );

        if (res.data.status === "success") {
          this.message = "Password reset successful. Redirecting...";
          localStorage.removeItem('forget_email');
          return true;
        } else {
          this.message = res.data.message || "Something went wrong!";
          return false;
        }
      } catch (err) {
        this.message = "Request failed: " + err.response?.data?.message;
        this.loading = false;
        return false;
      } finally {
        this.loading = false;
      }
    },

    async verifyOtp(email, otp) {
      this.loading = true;
      this.errors = {};
      this.message = "Matching...";

      try {
        const res = await axios.post(
          "/api/verification", {email, otp}, 
          {
            headers: {
              Accept: "application/json",
              "Content-Type": "application/json",
            }
          }
        );

        if (res.data.status === "success") {
          localStorage.removeItem("signup_email");
          this.message = res.data.message;
          return { ok: true, redirect: "/login" };
        }
        return { ok: false };
      } catch (err) {
        this.message = err.response?.data?.message;
        this.loading = false;
        return { ok: false };
      } finally {
        this.loading = false;
      }
    }
  }
});
