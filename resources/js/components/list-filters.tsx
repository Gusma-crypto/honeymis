import { router, useForm, usePage } from '@inertiajs/react';
import { Filter, RotateCcw, Search } from 'lucide-react';
import { Errors, inputClass } from '@/components/commerce-ui';
import { Button } from '@/components/ui/button';

type Props = {
    statuses?: Record<string, string>;
    placeholder?: string;
    filterLabel?: string;
    extraField?: { name: string; label: string; value: string };
};
export function ListFilters(props: Props) {
    const page = usePage();
    const filters = page.props.listFilters as
        | { search: string; status: string }
        | undefined;
    return (
        <FilterForm
            key={page.url}
            {...props}
            url={page.url.split('?')[0]}
            values={filters ?? { search: '', status: '' }}
        />
    );
}
function FilterForm({
    statuses = {},
    placeholder = 'Cari data...',
    filterLabel = 'Status',
    extraField,
    url,
    values,
}: Props & { url: string; values: { search: string; status: string } }) {
    const form = useForm<Record<string, string>>({
        ...values,
        ...(extraField ? { [extraField.name]: extraField.value } : {}),
    });
    const active = Object.values(form.data).some(Boolean);
    return (
        <form
            role="search"
            aria-label="Pencarian dan filter daftar"
            className="grid gap-3 rounded-xl border border-primary/15 bg-card p-4 shadow-sm"
            onSubmit={(event) => {
                event.preventDefault();
                form.get(url, {
                    preserveScroll: true,
                    preserveState: true,
                    replace: true,
                });
            }}
        >
            <div className="flex flex-wrap items-end gap-3">
                <label className="min-w-48 flex-1 space-y-2 text-xs font-medium">
                    <span>Pencarian</span>
                    <span className="relative block">
                        <Search className="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground" />
                        <input
                            type="search"
                            maxLength={100}
                            className={`${inputClass} pl-9`}
                            value={form.data.search}
                            placeholder={placeholder}
                            onChange={(e) =>
                                form.setData('search', e.target.value)
                            }
                        />
                    </span>
                </label>
                {Object.keys(statuses).length > 0 && (
                    <label className="min-w-40 space-y-2 text-xs font-medium">
                        <span>{filterLabel}</span>
                        <select
                            className={inputClass}
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData('status', e.target.value)
                            }
                        >
                            <option value="">Semua</option>
                            {Object.entries(statuses).map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </select>
                    </label>
                )}
                {extraField && (
                    <label className="min-w-40 space-y-2 text-xs font-medium">
                        <span>{extraField.label}</span>
                        <input
                            className={inputClass}
                            maxLength={100}
                            value={form.data[extraField.name]}
                            onChange={(e) =>
                                form.setData(extraField.name, e.target.value)
                            }
                        />
                    </label>
                )}
                <Button disabled={form.processing}>
                    <Filter className="size-4" />
                    {form.processing ? 'Memuat...' : 'Terapkan'}
                </Button>
                {active && (
                    <Button
                        type="button"
                        variant="outline"
                        disabled={form.processing}
                        onClick={() =>
                            router.get(
                                url,
                                {},
                                { preserveScroll: true, replace: true },
                            )
                        }
                    >
                        <RotateCcw className="size-4" />
                        Reset
                    </Button>
                )}
            </div>
            <Errors errors={form.errors} />
        </form>
    );
}
