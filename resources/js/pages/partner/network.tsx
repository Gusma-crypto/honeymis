import { Link } from '@inertiajs/react';
import { NetworkWorkspace } from '@/components/network-workspace';
import type { PartnerNetworkData } from '@/components/partner-network-summary';
import { DataTable, StatusBadge } from '@/components/data-list';
import { MemberAvatar } from '@/components/member-avatar';
import { dashboard } from '@/routes/partner';

export default function PartnerNetwork({
    networkSummary,
    ranks,
    ownSummary,
}: {
    networkSummary: PartnerNetworkData;
    ranks: Record<number, string>;
    ownSummary: {
        rank: string;
        personal_sales: number;
        team_sales: number;
        commissions: number;
    };
}) {
    return (
        <NetworkWorkspace
            data={networkSummary}
            performance={null}
            ranks={ranks}
            ownSummary={ownSummary}
            addRoot={null}
            actions={() => (
                <Link
                    className="text-sm font-semibold text-primary underline"
                    href={dashboard.url() + '#referral'}
                >
                    Lihat tautan referral saya
                </Link>
            )}
        >
            <section className="honey-panel partner-downline-list">
                <h2>Daftar Downline Saya</h2>
                <p>
                    Jaringan yang tampil hanya mitra di bawah akun Anda. Data
                    komisi pribadi mitra lain tidak ditampilkan.
                </p>
                <DataTable
                    label="Downline saya"
                    searchable
                    rows={networkSummary.nodes.filter(
                        (node) => node.id !== networkSummary.root_id,
                    )}
                    rowKey={(node) => node.id}
                    columns={[
                        {
                            header: 'Nama Mitra',
                            render: (node) => (
                                <div className="partner-member-name">
                                    <MemberAvatar
                                        name={node.name}
                                        gender={node.gender}
                                        photo={node.avatar}
                                    />
                                    <strong>{node.name}</strong>
                                </div>
                            ),
                        },
                        {
                            header: 'Kode Referral',
                            render: (node) => node.referral_code ?? '—',
                        },
                        {
                            header: 'Generasi',
                            render: (node) => `Level ${node.level}`,
                        },
                        {
                            header: 'Peringkat',
                            render: (node) => (
                                <span
                                    className={`network-rank rank-${ranks[node.id] ?? 'Member'}`}
                                >
                                    {ranks[node.id] ?? 'Member'}
                                </span>
                            ),
                        },
                        {
                            header: 'Status',
                            render: (node) => (
                                <StatusBadge status={node.status} />
                            ),
                        },
                    ]}
                />
            </section>
        </NetworkWorkspace>
    );
}
