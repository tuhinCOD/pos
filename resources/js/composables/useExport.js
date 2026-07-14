import { ref, onUnmounted } from 'vue';
import axios from 'axios';

export function useExport(channelName) {
    const pendingExport = ref(null);
    let pollTimer = null;

    const triggerDownload = (filename) => {
        window.location.href = `/api/${channelName}/download/${filename}`;
        pendingExport.value = null;
    };

    const pollForFile = (filename) => {
        pollTimer = setInterval(async () => {
            try {
                const res = await axios.get(`/api/export-check/${filename}`);
                if (res.data.ready) {
                    clearInterval(pollTimer);
                    triggerDownload(filename);
                }
            } catch {
                // file not ready yet, keep polling
            }
        }, 2000);
    };

    const handleExportStarted = (filename) => {
        if (pollTimer) clearInterval(pollTimer);
        pendingExport.value = filename;
        pollForFile(filename);
    };

    onUnmounted(() => {
        if (pollTimer) {
            clearInterval(pollTimer);
        }
    });

    return {
        pendingExport,
        handleExportStarted,
        triggerDownload,
    };
}
