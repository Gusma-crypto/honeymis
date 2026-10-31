import type { ReactNode } from 'react';
import type { LucideIcon } from 'lucide-react';

export function MenuTitle({
    icon: Icon,
    tone = 'blue',
    children,
    title,
    description,
}: {
    icon: LucideIcon;
    tone?: 'blue' | 'green' | 'amber' | 'coral' | 'violet';
    children?: ReactNode;
    title?: string;
    description?: string;
}) {
    return (
        <div className={`honey-menu-heading honey-tone-${tone}`}>
            <h1>
                <span className="honey-menu-icon" aria-hidden="true">
                    <Icon size={25} strokeWidth={2} />
                </span>
                <span>{title ?? children}</span>
            </h1>
            {description && <p>{description}</p>}
        </div>
    );
}
