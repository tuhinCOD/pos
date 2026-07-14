<template>
  <div class="container my-4">
    <h4 class="fw-bold mb-4"><i class="bi bi-person me-2"></i>My Profile</h4>

    <div v-if="!auth.user" class="text-center py-5">
      <p>Please <router-link to="/login">login</router-link> to view profile</p>
    </div>

    <div v-else class="row justify-content-center">
      <div class="col-md-6">
        <div class="card border-0 shadow-sm">
          <div class="card-body p-4">
            <div class="text-center mb-4">
              <i class="bi bi-person-circle display-1 text-primary"></i>
            </div>

            <div v-if="message" class="alert alert-success py-2 small">{{ message }}</div>
            <div v-if="error" class="alert alert-danger py-2 small">{{ error }}</div>

            <div class="mb-3">
              <label class="form-label small fw-semibold">Name</label>
              <input type="text" class="form-control" v-model="form.name">
            </div>
            <div class="mb-3">
              <label class="form-label small fw-semibold">Email</label>
              <input type="email" class="form-control" v-model="form.email">
            </div>
            <div class="mb-3">
              <label class="form-label small fw-semibold">Phone</label>
              <input type="text" class="form-control" v-model="form.phone" placeholder="Phone number">
            </div>
            <div class="mb-3">
              <label class="form-label small fw-semibold">City</label>
              <select class="form-select" v-model="form.city_id">
                <option value="">Select City</option>
                <option v-for="city in cities" :key="city.id" :value="city.id">{{ city.name }}</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label small fw-semibold">Address</label>
              <textarea class="form-control" v-model="form.address" rows="2" placeholder="Your address"></textarea>
            </div>

            <button class="btn btn-primary w-100" @click="updateProfile" :disabled="saving">
              {{ saving ? 'Saving...' : 'Update Profile' }}
            </button>
          </div>
        </div>

        <div class="card border-0 shadow-sm mt-3">
          <div class="card-body p-4">
            <h5 class="fw-bold mb-3">Account Details</h5>
            <p class="mb-0 small"><strong>Member since:</strong> {{ new Date(auth.user.created_at).toLocaleDateString() }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { useAuth } from '@/stores/auth';

const auth = useAuth();

const form = ref({ name: '', email: '', phone: '', city_id: '', address: '' });
const cities = ref([]);
const message = ref('');
const error = ref('');
const saving = ref(false);

onMounted(async () => {
  if (auth.user) {
    form.value.name = auth.user.name || '';
    form.value.email = auth.user.email || '';
    form.value.phone = auth.user.contact || '';
    form.value.city_id = auth.user.city_id || '';
    form.value.address = auth.user.address || '';
  }
  try {
    const res = await axios.get('/api/storefront/cities');
    if (res.data.status === 'success') {
      cities.value = res.data.cities;
    }
  } catch (e) {
    // silently fail
  }
});

const updateProfile = async () => {
  saving.value = true;
  message.value = '';
  error.value = '';
  try {
    const res = await axios.post('/api/storefront/update-profile', form.value);
    if (res.data.status === 'success') {
      message.value = 'Profile updated successfully';
      auth.user.name = form.value.name;
      auth.user.email = form.value.email;
      auth.user.city_id = form.value.city_id;
      auth.user.address = form.value.address;
    }
  } catch (e) {
    error.value = e.response?.data?.message || 'Update failed';
  } finally {
    saving.value = false;
  }
};
</script>
