import type { PageLinks } from '@/components/commerce-ui';
export type Variant = {
    id: number;
    sku: string;
    label: string;
    price: number;
    discount_amount: number;
    available: number;
};
export type CatalogProduct = {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    category: string;
    weight_g: number;
    image_url: string | null;
    variants: Variant[];
};
export type Paginated<T> = PageLinks & { data: T[] };
export type ShippingDestination = {
    id: number;
    label: string;
    province: string;
    city: string;
    district: string;
    subdistrict: string;
    postal_code: string;
};
export type ShippingSnapshot = {
    provider: 'manual' | 'rajaongkir';
    service: string;
    amount: number;
    weight_g: number;
    courier?: string;
    etd?: string;
    destination?: ShippingDestination;
};
export type ShippingOption = {
    token: string;
    courier: string;
    service: string;
    amount: number;
    etd: string;
    expires_at: string;
    quote: Quote;
    destination: ShippingDestination;
};
export type Quote = {
    cart_id: number;
    weight_g: number;
    shipping_required: boolean;
    shipping: ShippingSnapshot;
    lines: {
        variant_id: number;
        name: string;
        sku: string;
        quantity: number;
        unit_price: number;
        discount: number;
        total: number;
    }[];
    subtotal: number;
    discount_total: number;
    shipping_total: number;
    tax_total: number;
    grand_total: number;
    fingerprint: string;
};
