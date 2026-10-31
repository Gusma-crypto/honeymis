import { MenuTitle } from '@/components/menu-title';
import { Settings as MenuIcon } from 'lucide-react';
import { ShippingSettingsForm } from '@/components/shipping-settings-form';
import type { ShippingConfiguration } from '@/components/shipping-settings-form';
import { DataTable, DetailFields, RecordDialog } from '@/components/data-list';
import { Head } from '@inertiajs/react';
export default function Settings({
    settings,
    shippingSettings,
    canManageShipping,
}: {
    settings: Record<string, string | number>;
    shippingSettings: ShippingConfiguration;
    canManageShipping: boolean;
}) {
    return (
        <>
            <Head title="Pengaturan toko" />
            <main className="grid gap-5 p-6">
                <MenuTitle icon={MenuIcon} tone="blue">
                    Konfigurasi toko
                </MenuTitle>
                <p>
                    Ongkir manual dan API dikelola melalui pengaturan di bawah.
                    Tarif komisi tetap memakai halaman aturan komisi dengan
                    simulasi dan versi.
                </p>
                {canManageShipping && (
                    <ShippingSettingsForm
                        key={shippingSettings.revision}
                        settings={shippingSettings}
                    />
                )}
                <DataTable
                    label="Konfigurasi toko"
                    searchable
                    localFilter={{
                        label: 'Kelompok',
                        options: {
                            commission: 'Komisi & mitra',
                            payment: 'Pembayaran',
                            general: 'Umum',
                        },
                        value: (row) =>
                            /komisi|referral|payout/i.test(row.name)
                                ? 'commission'
                                : /bayar|ongkir/i.test(row.name)
                                  ? 'payment'
                                  : 'general',
                    }}
                    rows={Object.entries(settings).map(([name, value]) => ({
                        name,
                        value,
                    }))}
                    rowKey={(r) => r.name}
                    columns={[
                        { header: 'Pengaturan', render: (r) => r.name },
                        {
                            header: 'Nilai',
                            render: (r) => (
                                <span className="break-words">{r.value}</span>
                            ),
                        },
                        {
                            header: 'Detail',
                            render: (r) => (
                                <RecordDialog title={r.name}>
                                    <DetailFields
                                        rows={[
                                            ['Pengaturan', r.name],
                                            ['Nilai', r.value],
                                        ]}
                                    />
                                </RecordDialog>
                            ),
                        },
                    ]}
                />
            </main>
        </>
    );
}
