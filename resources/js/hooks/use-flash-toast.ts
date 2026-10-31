import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { showNotification } from '@/lib/sweet-alert';
import type { FlashToast } from '@/types/ui';

export function useFlashToast(): void {
    useEffect(() => {
        let mutation = false;
        let hasFlash = false;
        const removeStart = router.on('start', (event) => {
            mutation = event.detail.visit.method !== 'get';
            hasFlash = false;
        });
        const removeFlash = router.on('flash', (event) => {
            const data = event.detail.flash.toast as FlashToast | undefined;
            if (data) {
                hasFlash = true;
                void showNotification(data);
            }
        });
        const removeSuccess = router.on('success', () => {
            if (mutation && !hasFlash) {
                void showNotification({
                    type: 'success',
                    message: 'Perubahan berhasil disimpan.',
                });
            }
            mutation = false;
        });
        const removeError = router.on('error', () => {
            mutation = false;
            if (!hasFlash) {
                void showNotification({
                    type: 'error',
                    message:
                        'Data belum tersimpan. Periksa pesan kesalahan pada form.',
                });
            }
        });
        return () => {
            removeStart();
            removeFlash();
            removeSuccess();
            removeError();
        };
    }, []);
}
