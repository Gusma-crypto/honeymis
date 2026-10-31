import { ListFilters } from '@/components/list-filters';
import { confirmAction } from '@/lib/sweet-alert';
import {
    Errors,
    Field,
    inputClass,
    useRequestKey,
} from '@/components/commerce-ui';
import PasswordInput from '@/components/password-input';
import {
    DataTable,
    DetailFields,
    RecordDialog,
    RowActions,
    StatusBadge,
} from '@/components/data-list';
import { Pager } from '@/components/commerce-ui';
import { Head, useForm } from '@inertiajs/react';
import { useRef } from 'react';
import type { FormEvent } from 'react';
import { MenuTitle } from '@/components/menu-title';
import { ShieldCheck as MenuIcon } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { index, store } from '@/routes/admin/users';
import { update } from '@/routes/admin/users/access';

type ManagedUser = {
    id: number;
    name: string;
    email: string;
    status: 'active' | 'suspended';
    roles: string[];
    canUpdate: boolean;
};

type Props = {
    users: {
        data: ManagedUser[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    availableRoles: string[];
};

const roleLabels: Record<string, string> = {
    super_admin: 'Super Admin',
    store_admin: 'Admin Toko',
    finance: 'Finance',
    partner: 'Mitra',
    customer: 'Customer',
};

function UserAccessForm({
    user,
    availableRoles,
}: {
    user: ManagedUser;
    availableRoles: string[];
}) {
    const requestKey = useRef<string | null>(null);
    const form = useForm({
        roles: user.roles,
        status: user.status,
        idempotency_key: '',
    });

    async function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (
            form.processing ||
            !(await confirmAction(
                'Simpan perubahan akses pengguna?',
                `${user.name}: periksa role dan status akun sebelum melanjutkan.`,
                form.data.status === 'suspended',
            ))
        )
            return;
        requestKey.current ??= crypto.randomUUID();
        form.transform((data) => ({
            ...data,
            idempotency_key: requestKey.current ?? '',
        }));
        form.patch(update.url(user.id), {
            preserveScroll: true,
            onSuccess: () => {
                requestKey.current = null;
            },
        });
    }

    return (
        <form
            onSubmit={submit}
            className="flex flex-col gap-4 rounded-xl border p-5"
        >
            <div>
                <h2 className="font-semibold">{user.name}</h2>
                <p className="text-sm text-muted-foreground">{user.email}</p>
            </div>
            <fieldset
                disabled={!user.canUpdate || form.processing}
                className="flex flex-col gap-4 disabled:opacity-60"
            >
                <legend className="mb-2 text-sm font-medium">
                    Role pengguna
                </legend>
                <div className="flex flex-wrap gap-4">
                    {availableRoles.map((role) => (
                        <label
                            key={role}
                            className="flex items-center gap-2 text-sm"
                        >
                            <input
                                type="checkbox"
                                checked={form.data.roles.includes(role)}
                                onChange={(event) =>
                                    form.setData(
                                        'roles',
                                        event.target.checked
                                            ? [...form.data.roles, role]
                                            : form.data.roles.filter(
                                                  (assignedRole) =>
                                                      assignedRole !== role,
                                              ),
                                    )
                                }
                            />
                            {roleLabels[role] ?? role}
                        </label>
                    ))}
                </div>
                <InputError message={form.errors.roles} />
                <div className="flex flex-col gap-2">
                    <Label htmlFor={`status-${user.id}`}>Status akun</Label>
                    <select
                        id={`status-${user.id}`}
                        value={form.data.status}
                        onChange={(event) =>
                            form.setData(
                                'status',
                                event.target.value as ManagedUser['status'],
                            )
                        }
                        className="h-10 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="active">Aktif</option>
                        <option value="suspended">Dinonaktifkan</option>
                    </select>
                    <InputError message={form.errors.status} />
                </div>
                <InputError message={form.errors.idempotency_key} />
                {user.canUpdate && (
                    <Button
                        type="submit"
                        disabled={form.processing}
                        className="self-start"
                    >
                        {form.processing ? 'Menyimpan...' : 'Simpan akses'}
                    </Button>
                )}
            </fieldset>
            {!user.canUpdate && (
                <p className="text-sm text-muted-foreground">
                    Akses akun sendiri tidak dapat diubah dari halaman ini.
                </p>
            )}
        </form>
    );
}

function CreateUserForm({ availableRoles }: { availableRoles: string[] }) {
    const key = useRequestKey();
    const form = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        roles: ['customer'] as string[],
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
                form.post(store.url(), {
                    onSuccess: () => {
                        key.clear();
                        form.reset();
                    },
                });
            }}
        >
            <Field label="Nama lengkap">
                <input
                    className={inputClass}
                    required
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    autoComplete="name"
                />
            </Field>
            <Field label="Email">
                <input
                    className={inputClass}
                    type="email"
                    required
                    value={form.data.email}
                    onChange={(event) =>
                        form.setData('email', event.target.value)
                    }
                    autoComplete="email"
                />
            </Field>
            <Field label="Password">
                <PasswordInput
                    required
                    value={form.data.password}
                    onChange={(event) =>
                        form.setData('password', event.target.value)
                    }
                    autoComplete="new-password"
                />
            </Field>
            <Field label="Konfirmasi password">
                <PasswordInput
                    required
                    value={form.data.password_confirmation}
                    onChange={(event) =>
                        form.setData(
                            'password_confirmation',
                            event.target.value,
                        )
                    }
                    autoComplete="new-password"
                />
            </Field>
            <fieldset className="rounded-xl border p-4">
                <legend className="px-1 text-sm font-medium">
                    Role pengguna
                </legend>
                <div className="flex flex-wrap gap-4">
                    {availableRoles.map((role) => (
                        <label
                            className="flex items-center gap-2 text-sm"
                            key={role}
                        >
                            <input
                                type="checkbox"
                                checked={form.data.roles.includes(role)}
                                onChange={(event) =>
                                    form.setData(
                                        'roles',
                                        event.target.checked
                                            ? [...form.data.roles, role]
                                            : form.data.roles.filter(
                                                  (value) => value !== role,
                                              ),
                                    )
                                }
                            />
                            {roleLabels[role] ?? role}
                        </label>
                    ))}
                </div>
            </fieldset>
            <p className="text-xs text-muted-foreground">
                Pemilik akun perlu memverifikasi email sebelum mengakses halaman
                yang dilindungi.
            </p>
            <Errors errors={form.errors} />
            <Button disabled={form.processing}>
                {form.processing ? 'Menyimpan...' : 'Simpan pengguna'}
            </Button>
        </form>
    );
}

export default function UserAccessIndex({ users, availableRoles }: Props) {
    return (
        <>
            <Head title="Akses pengguna" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <MenuTitle
                    icon={MenuIcon}
                    tone="violet"
                    title="Akses pengguna"
                    description="Kelola peran, izin akses, dan status akun. Setiap perubahan tercatat dalam audit."
                />
                <div className="flex justify-end">
                    <RecordDialog
                        kind="add"
                        label="Tambah pengguna"
                        title="Tambah pengguna"
                    >
                        <CreateUserForm availableRoles={availableRoles} />
                    </RecordDialog>
                </div>
                <ListFilters
                    placeholder="Nama, email, atau role pengguna"
                    statuses={{ active: 'Aktif', suspended: 'Ditangguhkan' }}
                />
                <DataTable
                    label="Daftar pengguna"
                    rows={users.data}
                    rowKey={(u) => u.id}
                    columns={[
                        {
                            header: 'Nama',
                            render: (u) => (
                                <span className="font-medium">{u.name}</span>
                            ),
                        },
                        { header: 'Email', render: (u) => u.email },
                        {
                            header: 'Role',
                            render: (u) =>
                                u.roles
                                    .map((role) => roleLabels[role] ?? role)
                                    .join(', '),
                        },
                        {
                            header: 'Status',
                            render: (u) => <StatusBadge status={u.status} />,
                        },
                        {
                            header: 'Aksi',
                            render: (u) => (
                                <RowActions>
                                    <RecordDialog title={`Detail ${u.name}`}>
                                        <DetailFields
                                            rows={[
                                                ['Nama', u.name],
                                                ['Email', u.email],
                                                [
                                                    'Role',
                                                    u.roles
                                                        .map(
                                                            (r) =>
                                                                roleLabels[r] ??
                                                                r,
                                                        )
                                                        .join(', '),
                                                ],
                                                [
                                                    'Status',
                                                    <StatusBadge
                                                        status={u.status}
                                                    />,
                                                ],
                                            ]}
                                        />
                                    </RecordDialog>
                                    {u.canUpdate && (
                                        <RecordDialog
                                            kind="edit"
                                            title={`Edit akses ${u.name}`}
                                        >
                                            <UserAccessForm
                                                user={u}
                                                availableRoles={availableRoles}
                                            />
                                        </RecordDialog>
                                    )}
                                </RowActions>
                            ),
                        },
                    ]}
                />
                <Pager page={users} />
            </div>
        </>
    );
}

UserAccessIndex.layout = {
    breadcrumbs: [{ title: 'Akses pengguna', href: index() }],
};
