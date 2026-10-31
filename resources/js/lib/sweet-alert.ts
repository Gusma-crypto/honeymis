import type { FlashToast } from '@/types/ui';

let confirming = false;

async function alertInstance() {
    const { default: Swal } = await import('sweetalert2');
    return Swal;
}

export async function confirmAction(
    title: string,
    text: string,
    destructive = false,
): Promise<boolean> {
    if (confirming) return false;
    confirming = true;
    try {
        const Swal = await alertInstance();
        const target =
            document.querySelector<HTMLElement>(
                '[data-slot="dialog-content"][data-state="open"]',
            ) ?? document.body;
        const result = await Swal.fire({
            titleText: title,
            text,
            icon: destructive ? 'warning' : 'question',
            target,
            showCancelButton: true,
            confirmButtonText: 'Ya, lanjutkan',
            cancelButtonText: 'Batal',
            focusCancel: true,
            reverseButtons: true,
            heightAuto: false,
            keydownListenerCapture: true,
            buttonsStyling: false,
            customClass: {
                popup: 'honey-alert',
                confirmButton: destructive
                    ? 'honey-alert-danger'
                    : 'honey-alert-confirm',
                cancelButton: 'honey-alert-cancel',
            },
        });
        return result.isConfirmed;
    } finally {
        confirming = false;
    }
}

export async function showNotification(data: FlashToast): Promise<void> {
    const Swal = await alertInstance();
    await Swal.fire({
        toast: true,
        position: 'top-end',
        icon: data.type,
        titleText: data.message,
        showConfirmButton: false,
        timer: data.type === 'error' ? 7000 : 4500,
        timerProgressBar: true,
        customClass: { popup: 'honey-alert' },
        didOpen: (popup) => {
            popup.addEventListener('mouseenter', Swal.stopTimer);
            popup.addEventListener('mouseleave', Swal.resumeTimer);
        },
    });
}
