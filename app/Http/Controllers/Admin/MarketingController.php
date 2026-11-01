<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Catalog\Models\Product;
use App\Domains\Commissions\Services\MlmProgramSettings;
use App\Domains\Partners\Models\Partner;
use App\Domains\Partners\Services\ReferralCapture;
use App\Domains\Shared\Services\AuditWriter;
use App\Http\Controllers\Controller;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MarketingController extends Controller
{
    private function authorizeView(Request $request): void
    {
        abort_unless($request->user()?->can('reports.view'), 403);
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()?->can('catalog.manage') || $request->user()?->can('commission-rules.manage'), 403);
    }

    public function index(Request $request, MlmProgramSettings $settings): Response
    {
        $this->authorizeView($request);
        $request->validate(['period' => ['nullable', 'date_format:Y-m']]);
        $period = $request->input('period') ?: now('Asia/Jakarta')->format('Y-m');
        $from = CarbonImmutable::createFromFormat('!Y-m', $period, 'Asia/Jakarta')->startOfMonth();
        $until = $from->addMonth();
        $visits = DB::table('marketing_visits')->where('created_at', '>=', $from->utc())->where('created_at', '<', $until->utc());
        $registrations = (clone $visits)->whereIn('registered_user_id', DB::table('users')->select('id')->whereNotNull('email_verified_at'))->count();
        $sales = $this->sales($from, $until);
        $salesTotals = DB::query()->fromSub(clone $sales, 'sales')->selectRaw('COUNT(*) as orders, COALESCE(SUM(revenue), 0) as revenue')->first();
        $converted = DB::query()->fromSub(clone $sales, 'sales')->join('marketing_visits as visits', 'visits.id', '=', 'sales.marketing_visit_id')
            ->join('users', 'users.id', '=', 'visits.registered_user_id')->join('customers', 'customers.id', '=', 'sales.customer_id')->whereNotNull('users.email_verified_at')
            ->whereColumn('customers.user_id', 'users.id')->where('visits.created_at', '>=', $from->utc())->where('visits.created_at', '<', $until->utc())->distinct()->count('visits.id');
        $campaigns = DB::table('marketing_campaigns')->leftJoin('products', 'products.id', '=', 'marketing_campaigns.product_id')->select('marketing_campaigns.*', 'products.name as product_name')->orderByDesc('marketing_campaigns.id')->paginate(12)->withQueryString();
        $active = $this->activeCampaigns()->count();
        $trend = [];
        for ($day = 1; $day <= $from->daysInMonth; $day += 4) {
            $start = $from->addDays($day - 1);
            $end = $start->addDays(4)->min($until);
            $visitCount = DB::table('marketing_visits')->where('created_at', '>=', $start->utc())->where('created_at', '<', $end->utc())->count();
            $registered = DB::table('marketing_visits')->where('created_at', '>=', $start->utc())->where('created_at', '<', $end->utc())->whereIn('registered_user_id', DB::table('users')->select('id')->whereNotNull('email_verified_at'))->count();
            $orderCount = DB::query()->fromSub($this->sales($start, $end), 'sales')->count();
            $trend[] = ['label' => (string) $day, 'visits' => $visitCount, 'registrations' => $registered, 'orders' => $orderCount];
        }
        $partner = Partner::where('user_id', $request->user()->id)->where('status', 'active')->first();
        $top = DB::query()->fromSub($this->sales($from, $until), 'sales')->join('partners', 'partners.id', '=', 'sales.partner_referral_id')->join('users', 'users.id', '=', 'partners.user_id')
            ->select('users.name', 'users.gender', 'users.profile_photo_path')->selectRaw('COUNT(*) as orders, SUM(sales.revenue) as revenue')->groupBy('users.id', 'users.name', 'users.gender', 'users.profile_photo_path')->orderByDesc('revenue')->limit(3)->get()->map(static fn ($marketer): array => ['name' => $marketer->name, 'gender' => $marketer->gender, 'avatar' => $marketer->profile_photo_path === null ? null : Storage::disk('public')->url($marketer->profile_photo_path), 'orders' => (int) $marketer->orders, 'revenue' => (int) $marketer->revenue])->all();
        $featured = Product::where('status', 'active')->with('variants')->orderBy('id')->limit(3)->get()->map(static fn (Product $product): array => ['id' => $product->id, 'name' => $product->name, 'slug' => $product->slug, 'price' => $product->variants->min(fn ($variant) => $variant->price - $variant->discount_amount)])->all();
        $program = $settings->current();

        return Inertia::render('admin/marketing', [
            'period' => $period, 'stats' => ['registrations' => $registrations, 'revenue' => (int) $salesTotals->revenue, 'orders' => (int) $salesTotals->orders, 'visits' => (clone $visits)->count(), 'conversion_bps' => $registrations === 0 ? 0 : intdiv($converted * 10000, $registrations), 'active_campaigns' => $active],
            'campaigns' => $campaigns, 'featuredProducts' => $featured, 'products' => Product::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'referralUrl' => $partner === null ? null : route('referral.visit', $partner->referral_code), 'referralCode' => $partner?->referral_code,
            'storeUrl' => route('catalog.index'),
            'assets' => DB::table('marketing_assets')->orderByDesc('id')->limit(30)->get(['id', 'title', 'kind', 'mime_type', 'created_at']),
            'drafts' => DB::table('marketing_broadcast_drafts')->where('created_by', $request->user()->id)->orderByDesc('id')->limit(20)->get(['id', 'title', 'message', 'audience', 'created_at']),
            'activity' => DB::table('audit_logs')->whereIn('action', ['marketing_campaign_saved', 'marketing_asset_uploaded', 'marketing_broadcast_saved'])->orderByDesc('id')->limit(5)->get(['action', 'subject_id', 'created_at']),
            'topMarketers' => $top, 'trend' => $trend, 'rankRequirements' => $program->rank_requirements, 'referralBps' => $program->referral_first_order_bps,
        ]);
    }

    private function activeCampaigns(): Builder
    {
        return DB::table('marketing_campaigns')->where('status', 'active')->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    private function sales(CarbonImmutable $from, CarbonImmutable $until): Builder
    {
        $payments = DB::table('payments')->select('order_id')->selectRaw('SUM(amount) as paid')->where('status', 'verified')->whereNotNull('verified_at')->groupBy('order_id');
        $refunds = DB::table('refunds')->select('order_item_id')->selectRaw('SUM(amount) as refunded')->where('status', 'processed')->groupBy('order_item_id');
        $line = 'order_items.unit_price_snapshot * order_items.quantity - order_items.discount_snapshot - COALESCE(refunds.refunded, 0)';

        return DB::table('orders')->joinSub($payments, 'payments', 'payments.order_id', '=', 'orders.id')->join('order_items', 'order_items.order_id', '=', 'orders.id')->leftJoinSub($refunds, 'refunds', 'refunds.order_item_id', '=', 'order_items.id')
            ->whereNotNull('orders.marketing_visit_id')->whereColumn('payments.paid', 'orders.grand_total')->where('orders.fraud_hold', false)->whereIn('orders.status', ['paid', 'processing', 'shipped', 'delivered', 'completed'])
            ->where('orders.placed_at', '>=', $from->utc())->where('orders.placed_at', '<', $until->utc())->select('orders.id', 'orders.marketing_visit_id', 'orders.partner_referral_id', 'orders.customer_id')->selectRaw('SUM(CASE WHEN '.$line.' > 0 THEN '.$line.' ELSE 0 END) as revenue')->groupBy('orders.id', 'orders.marketing_visit_id', 'orders.partner_referral_id', 'orders.customer_id');
    }

    public function campaign(Request $request, AuditWriter $audit, ?int $campaign = null): RedirectResponse
    {
        $this->authorizeManage($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string', 'max:1500'], 'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->where('status', 'active')], 'status' => ['required', 'in:draft,active,paused,completed'], 'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after:starts_at']]);
        foreach (['starts_at', 'ends_at'] as $field) {
            $data[$field] = empty($data[$field]) ? null : CarbonImmutable::parse($data[$field], 'Asia/Jakarta')->utc();
        }
        DB::transaction(function () use ($request, $audit, $campaign, $data): void {
            if ($campaign === null) {
                $id = DB::table('marketing_campaigns')->insertGetId([...$data, 'code' => Str::lower((string) Str::ulid()), 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            } else {
                abort_if(DB::table('marketing_campaigns')->where('id', $campaign)->lockForUpdate()->first() === null, 404);
                DB::table('marketing_campaigns')->where('id', $campaign)->update([...$data, 'updated_at' => now()]);
                $id = $campaign;
            }
            $audit->record($request->user()->id, 'marketing_campaign_saved', 'marketing_campaigns', $id, 'marketing-campaign:'.Str::uuid(), $data);
        });

        return back()->with('success', 'Kampanye disimpan. Harga produk mengikuti konfigurasi katalog.');
    }

    public function visit(Request $request, string $code, ReferralCapture $referrals): RedirectResponse
    {
        $campaign = $this->activeCampaigns()->where('code', $code)->first();
        abort_if($campaign === null, 404);
        $token = $request->cookie('honey_referral') ?: $request->session()->get('honey_referral');
        if ($request->filled('ref')) {
            $token = $referrals->capture(substr($request->string('ref')->toString(), 0, 100), is_string($token) ? $token : null, $request->user()?->id);
            if ($token !== null) {
                $request->session()->put('honey_referral', $token);
            }
        }
        $visitor = $request->session()->get('honey_marketing_visitor');
        if (! is_string($visitor)) {
            $visitor = Str::random(64);
            $request->session()->put('honey_marketing_visitor', $visitor);
        }
        $hash = hash('sha256', $visitor);
        DB::table('marketing_visits')->insertOrIgnore(['campaign_id' => $campaign->id, 'visitor_hash' => $hash, 'partner_id' => $referrals->valid(is_string($token) ? $token : null, $request->user()?->id)?->partner_id, 'expires_at' => now()->addDays(30), 'created_at' => now(), 'updated_at' => now()]);
        $visit = DB::table('marketing_visits')->where('campaign_id', $campaign->id)->where('visitor_hash', $hash)->first();
        $previous = $request->session()->get('honey_marketing_visit');
        if (! is_int($previous) || ! DB::table('marketing_visits')->where('id', $previous)->where('expires_at', '>', now())->exists()) {
            $request->session()->put('honey_marketing_visit', (int) $visit->id);
        }
        $product = $campaign->product_id === null ? null : Product::whereKey($campaign->product_id)->where('status', 'active')->first();
        $response = $product === null ? to_route('catalog.index') : to_route('catalog.show', $product->slug);

        return is_string($token) ? $response->withCookie(cookie('honey_referral', $token, config('partners.referral_days') * 1440, '/', null, $request->isSecure(), true, false, 'lax')) : $response;
    }

    public function assetUpload(Request $request, AuditWriter $audit): RedirectResponse
    {
        $this->authorizeManage($request);
        $request->validate(['title' => ['required', 'string', 'max:150'], 'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf,mp4', 'max:10240']]);
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);
        $path = $file->store('marketing-assets', 'local');
        abort_if($path === false, 500);
        try {
            DB::transaction(function () use ($request, $file, $path, $audit): void {
                $mime = $file->getMimeType() ?? 'application/octet-stream';
                $id = DB::table('marketing_assets')->insertGetId(['title' => $request->string('title')->toString(), 'kind' => str_starts_with($mime, 'image/') ? 'image' : ($mime === 'application/pdf' ? 'document' : 'video'), 'storage_key' => $path, 'mime_type' => $mime, 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
                $audit->record($request->user()->id, 'marketing_asset_uploaded', 'marketing_assets', $id, 'marketing-asset:'.$id);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return back()->with('success', 'Materi promosi berhasil diunggah.');
    }

    public function asset(Request $request, int $asset): StreamedResponse
    {
        $this->authorizeView($request);
        $record = DB::table('marketing_assets')->where('id', $asset)->first();
        abort_if($record === null, 404);

        return Storage::disk('local')->response($record->storage_key, headers: ['Content-Type' => $record->mime_type, 'X-Content-Type-Options' => 'nosniff'], disposition: $request->boolean('download') ? 'attachment' : 'inline');
    }

    public function draft(Request $request, AuditWriter $audit): RedirectResponse
    {
        $this->authorizeManage($request);
        $data = $request->validate(['title' => ['required', 'string', 'max:150'], 'message' => ['required', 'string', 'max:5000'], 'audience' => ['required', 'in:partners,customers,all']]);
        DB::transaction(function () use ($request, $data, $audit): void {
            $id = DB::table('marketing_broadcast_drafts')->insertGetId([...$data, 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            $audit->record($request->user()->id, 'marketing_broadcast_saved', 'marketing_broadcast_drafts', $id, 'marketing-broadcast:'.$id);
        });

        return back()->with('success', 'Draf tersimpan. Belum ada pesan yang dikirim.');
    }

    public function qr(Request $request): HttpResponse
    {
        $this->authorizeView($request);
        $partner = Partner::where('user_id', $request->user()->id)->where('status', 'active')->first();
        $url = $partner === null ? route('catalog.index') : route('referral.visit', $partner->referral_code);
        $svg = (new Writer(new ImageRenderer(new RendererStyle(240), new SvgImageBackEnd)))->writeString($url);

        return response($svg, 200, ['Content-Type' => 'image/svg+xml', 'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="honeycommerce-qr.svg"', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function demoAsset(Request $request, string $kind): HttpResponse
    {
        $this->authorizeView($request);
        $titles = ['catalog' => 'Katalog Produk Madu', 'flyer' => 'Madu Pilihan Keluarga', 'banner' => 'Rasakan Manisnya Kebaikan', 'post' => 'Madu Alami Setiap Hari', 'photo' => 'Pilihan Madu HoneyCommerce'];
        abort_unless(isset($titles[$kind]), 404);
        $contents = file_get_contents(public_path($kind === 'banner' ? 'honey-hero.jpg' : 'honey-product.jpg'));
        abort_if($contents === false, 404);
        $image = 'data:image/jpeg;base64,'.base64_encode($contents);
        $title = htmlspecialchars($titles[$kind], ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="800" viewBox="0 0 1200 800"><defs><linearGradient id="bg"><stop stop-color="#fff0bb"/><stop offset="1" stop-color="#ffcf64"/></linearGradient></defs><rect width="1200" height="800" rx="30" fill="url(#bg)"/><image href="'.$image.'" x="590" y="110" width="560" height="570" preserveAspectRatio="xMidYMid slice"/><rect x="40" y="70" width="300" height="60" rx="30" fill="#eea000"/><text x="70" y="110" font-family="Arial,sans-serif" font-size="28" fill="white">HoneyCommerce</text><text x="60" y="245" font-family="Arial,sans-serif" font-size="38" font-weight="bold" fill="#582600">'.$title.'</text><text x="60" y="315" font-family="Arial,sans-serif" font-size="27" fill="#664121">Madu pilihan untuk keluarga.</text><text x="60" y="365" font-family="Arial,sans-serif" font-size="25" fill="#664121">Temukan produk favorit Anda</text><text x="60" y="405" font-family="Arial,sans-serif" font-size="25" fill="#664121">di katalog HoneyCommerce.</text><rect x="60" y="470" width="300" height="68" rx="34" fill="#ffb900"/><text x="100" y="513" font-family="Arial,sans-serif" font-size="26" font-weight="bold">Lihat Katalog</text><text x="60" y="730" font-family="Arial,sans-serif" font-size="20" fill="#79592e">Contoh materi promosi — sesuaikan dengan produk Anda.</text></svg>';

        return response($svg, 200, ['Content-Type' => 'image/svg+xml', 'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="honeycommerce-'.$kind.'.svg"', 'X-Content-Type-Options' => 'nosniff']);
    }
}
