import { useState } from 'react';

export function ProductImage({
    name,
    imageUrl,
    priority = false,
    className = '',
}: {
    name: string;
    imageUrl: string | null;
    priority?: boolean;
    className?: string;
}) {
    const [failedUrl, setFailedUrl] = useState<string | null>(null);
    const illustrative = !imageUrl || failedUrl === imageUrl;
    return (
        <figure className={`relative overflow-hidden bg-muted ${className}`}>
            <img
                src={illustrative ? '/honey-product.jpg' : imageUrl}
                alt={
                    illustrative ? `Ilustrasi kemasan madu untuk ${name}` : name
                }
                width={800}
                height={800}
                loading={priority ? 'eager' : 'lazy'}
                fetchPriority={priority ? 'high' : 'auto'}
                decoding="async"
                className="aspect-square size-full object-cover transition-transform duration-500 group-hover:scale-[1.045] group-focus-visible:scale-[1.045] motion-reduce:transition-none motion-reduce:group-hover:scale-100 motion-reduce:group-focus-visible:scale-100"
                onError={() => {
                    if (!illustrative) setFailedUrl(imageUrl);
                }}
            />
            {illustrative && (
                <figcaption className="absolute right-3 bottom-3 rounded-full border border-white/50 bg-white/90 px-2.5 py-1 text-[10px] font-medium text-emerald-950">
                    Gambar ilustrasi
                </figcaption>
            )}
        </figure>
    );
}
