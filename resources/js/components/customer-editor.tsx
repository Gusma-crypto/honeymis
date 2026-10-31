import { useForm } from '@inertiajs/react';
import {
    Errors,
    Field,
    inputClass,
    useRequestKey,
} from '@/components/commerce-ui';
import { Button } from '@/components/ui/button';
import { store, update } from '@/routes/admin/customers';
export type CustomerContact = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
};
export function CustomerEditor({ customer }: { customer?: CustomerContact }) {
    const key = useRequestKey();
    const form = useForm({
        name: customer?.name ?? '',
        email: customer?.email ?? '',
        phone: customer?.phone ?? '',
        idempotency_key: '',
    });
    return (
        <form
            className="grid gap-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.transform((data) => ({
                    ...data,
                    idempotency_key: key.get(),
                }));
                const options = {
                    onSuccess: () => {
                        key.clear();
                        form.reset();
                    },
                };
                if (customer) form.put(update.url(customer.id), options);
                else form.post(store.url(), options);
            }}
        >
            {(['name', 'email', 'phone'] as const).map((field, index) => (
                <Field
                    key={field}
                    label={
                        [
                            'Nama pelanggan',
                            'Email kontak',
                            'Telepon (opsional)',
                        ][index]
                    }
                >
                    <input
                        className={inputClass}
                        type={
                            field === 'email'
                                ? 'email'
                                : field === 'phone'
                                  ? 'tel'
                                  : 'text'
                        }
                        required={field !== 'phone'}
                        value={form.data[field]}
                        onChange={(event) =>
                            form.setData(field, event.target.value)
                        }
                    />
                </Field>
            ))}
            <p className="text-xs text-muted-foreground">
                Form ini mengelola kontak pelanggan. Akun login dikelola pada
                halaman Akses pengguna.
            </p>
            <Errors errors={form.errors} />
            <Button disabled={form.processing}>
                {form.processing ? 'Menyimpan...' : 'Simpan pelanggan'}
            </Button>
        </form>
    );
}
