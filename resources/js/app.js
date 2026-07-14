import './echo';

import { createApp, onMounted } from 'vue';
import { createPinia } from 'pinia'
import App from './App.vue';
import router from './router/index';
import print from 'vue3-print-nb';

import 'bootstrap-icons/font/bootstrap-icons.min.css';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap/dist/js/bootstrap.bundle.min.js';
import { useAuth } from './stores/auth';
import { useLoadingStore } from './stores/loading';
import axios from 'axios';

const pinia = createPinia();
const app = createApp(App);
app.use(pinia);
app.use(router);
app.use(print);

const loadingStore = useLoadingStore();

axios.interceptors.request.use((config) => {
    loadingStore.start();
    return config;
});

axios.interceptors.response.use(
    (response) => {
        loadingStore.stop();
        return response;
    },
    (error) => {
        loadingStore.stop();
        return Promise.reject(error);
    }
);

app.mount('#app');

const auth = useAuth();
onMounted(async() => {
    await auth.me();
})
