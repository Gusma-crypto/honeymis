import { MenuTitle } from '@/components/menu-title';
import { ChartNoAxesCombined as MenuIcon } from 'lucide-react';
import { Head, useForm } from '@inertiajs/react';
import { Errors, Field, inputClass, money } from '@/components/commerce-ui';
import { Button } from '@/components/ui/button';
import { update } from '@/routes/admin/mlm-settings';

type RankRequirements = Record<
    'Member' | 'Bronze' | 'Silver' | 'Gold' | 'Platinum',
    { personal_sales: number; team_sales: number }
>;

type ProgramForm = {
    referral_first_order_bps: number;
    generation_rates: number[];
    rank_requirements: RankRequirements;
};

const rankNames = ['Member', 'Bronze', 'Silver', 'Gold', 'Platinum'] as const;

export default function MlmSettings({
    version,
    referralFirstOrderBps,
    generationRates,
    rankRequirements,
}: {
    version: number;
    referralFirstOrderBps: number;
    generationRates: number[];
    rankRequirements: RankRequirements;
}) {
    const form = useForm<ProgramForm>({
        referral_first_order_bps: referralFirstOrderBps,
        generation_rates: generationRates,
        rank_requirements: rankRequirements,
    });

    return (
        <>
            <Head title="Program MLM" />
            <main className="mx-auto grid w-full max-w-6xl gap-6 p-6">
                <header className="grid gap-2">
                    <MenuTitle icon={MenuIcon} tone="blue">
                        Program MLM
                    </MenuTitle>
                    <p className="text-muted-foreground">
                        Versi konfigurasi {version}. Target omzet dihitung
                        berdasarkan transaksi produk yang dibayar dan dikurangi
                        refund; omzet tim hanya berasal dari downline.
                    </p>
                </header>

                <form
                    className="grid gap-6"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(update.url(), { preserveScroll: true });
                    }}
                >
                    <section className="grid gap-4 rounded-xl border p-5">
                        <h2 className="text-xl font-semibold">
                            Komisi penjualan
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Komisi direct 15–20% diatur pada menu Aturan komisi.
                            Bonus referral transaksi pertama menggantikan tarif
                            generasi 1, bukan tambahan di atasnya.
                        </p>
                        <p className="text-sm text-muted-foreground">
                            Total komisi dibatasi margin produk dan maksimum 60%
                            nilai penjualan produk. Ongkir, pajak, pendaftaran,
                            dan perekrutan anggota tidak menjadi dasar bonus.
                        </p>
                        <Field label="Referral transaksi pertama (%)">
                            <input
                                className={inputClass}
                                type="number"
                                min="0"
                                max="100"
                                step="0.01"
                                value={form.data.referral_first_order_bps / 100}
                                onChange={(event) =>
                                    form.setData(
                                        'referral_first_order_bps',
                                        Math.round(
                                            Number(event.target.value) * 100,
                                        ),
                                    )
                                }
                            />
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                            {form.data.generation_rates.map((rate, index) => (
                                <Field
                                    key={index}
                                    label={`Generasi ${index + 1} (%)`}
                                >
                                    <input
                                        className={inputClass}
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        value={rate / 100}
                                        onChange={(event) => {
                                            const rates = [
                                                ...form.data.generation_rates,
                                            ];
                                            rates[index] = Math.round(
                                                Number(event.target.value) *
                                                    100,
                                            );
                                            form.setData(
                                                'generation_rates',
                                                rates,
                                            );
                                        }}
                                    />
                                </Field>
                            ))}
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Sistem menolak tarif jika total komisi direct dan
                            jaringan melampaui margin produk aktif.
                        </p>
                    </section>

                    <section className="grid gap-4 rounded-xl border p-5">
                        <div>
                            <h2 className="text-xl font-semibold">
                                Target omzet peringkat bulanan
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Peringkat turun otomatis bila target bulan
                                berjalan tidak terpenuhi. Satuan dalam rupiah.
                            </p>
                        </div>
                        <div className="grid gap-4">
                            {rankNames.map((rank) => (
                                <div
                                    className="grid gap-4 rounded-lg border p-4 sm:grid-cols-3"
                                    key={rank}
                                >
                                    <h3 className="font-semibold">{rank}</h3>
                                    {(
                                        [
                                            ['personal_sales', 'Omzet pribadi'],
                                            ['team_sales', 'Omzet tim'],
                                        ] as const
                                    ).map(([metric, label]) => (
                                        <Field
                                            key={metric}
                                            label={`${label} — ${money(form.data.rank_requirements[rank][metric])}`}
                                        >
                                            <input
                                                className={inputClass}
                                                type="number"
                                                min="0"
                                                step="1000"
                                                value={
                                                    form.data.rank_requirements[
                                                        rank
                                                    ][metric]
                                                }
                                                onChange={(event) =>
                                                    form.setData(
                                                        'rank_requirements',
                                                        {
                                                            ...form.data
                                                                .rank_requirements,
                                                            [rank]: {
                                                                ...form.data
                                                                    .rank_requirements[
                                                                    rank
                                                                ],
                                                                [metric]:
                                                                    Math.max(
                                                                        0,
                                                                        Math.trunc(
                                                                            Number(
                                                                                event
                                                                                    .target
                                                                                    .value,
                                                                            ),
                                                                        ),
                                                                    ),
                                                            },
                                                        },
                                                    )
                                                }
                                            />
                                        </Field>
                                    ))}
                                </div>
                            ))}
                        </div>
                    </section>

                    <Errors errors={form.errors} />
                    <Button disabled={form.processing}>
                        {form.processing
                            ? 'Menyimpan...'
                            : 'Simpan konfigurasi'}
                    </Button>
                </form>
            </main>
        </>
    );
}
