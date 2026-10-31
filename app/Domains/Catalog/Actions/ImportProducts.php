<?php

namespace App\Domains\Catalog\Actions;

use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Services\ProductSpreadsheet;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImportProducts
{
    public function __construct(private ProductSpreadsheet $spreadsheet, private SaveProduct $products) {}

    public function handle(User $actor, UploadedFile $file): int
    {
        abort_unless($actor->can('catalog.manage'), 403);
        $rows = $this->spreadsheet->rows($file);

        return DB::transaction(function () use ($actor, $rows): int {
            $groups = [];
            foreach ($rows as $index => $row) {
                $category = Category::where('slug', $row['category_slug'])->first();
                if ($category === null) {
                    throw ValidationException::withMessages(['file' => 'Kategori belum tersedia pada baris '.($index + 2).'. Buat kategori dahulu.']);
                }
                $data = [
                    'category_id' => $category->id, 'sku' => $row['sku'], 'name' => $row['name'], 'slug' => Str::slug($row['sku'].'-'.$row['name']),
                    'description' => $row['description'], 'status' => $row['status'], 'weight_g' => $row['weight_g'], 'commission_eligible' => $row['commission_eligible'],
                    'variants' => [['id' => null, 'sku' => $row['variant_sku'], 'label' => $row['label'], 'price' => $row['price'], 'cost' => $row['cost'], 'discount_amount' => $row['discount_amount'], 'status' => $row['status']]],
                ];
                $data['variants'][0]['member_price'] = $row['member_price'] === '' ? null : $row['member_price'];
                $validator = Validator::make($data, (new ProductRequest)->rules());
                if ($validator->fails() || ProductVariant::where('sku', $row['variant_sku'])->exists()) {
                    throw ValidationException::withMessages(['file' => 'Baris '.($index + 2).' tidak valid: '.($validator->errors()->first() ?: 'SKU varian sudah digunakan.')]);
                }
                if (isset($groups[$row['sku']])) {
                    $existing = $groups[$row['sku']];
                    if (array_diff_key($existing, ['variants' => true]) !== array_diff_key($data, ['variants' => true])) {
                        throw ValidationException::withMessages(['file' => 'Data produk dengan SKU yang sama harus konsisten.']);
                    }
                    $groups[$row['sku']]['variants'][] = $data['variants'][0];
                } else {
                    $groups[$row['sku']] = $data;
                }
            }
            foreach ($groups as $data) {
                $validator = Validator::make($data, (new ProductRequest)->rules());
                if ($validator->fails()) {
                    throw ValidationException::withMessages(['file' => $validator->errors()->first()]);
                }
                $data['weight_g'] = (int) $data['weight_g'];
                $data['commission_eligible'] = (bool) $data['commission_eligible'];
                foreach ($data['variants'] as &$variant) {
                    $variant['member_price'] = $variant['member_price'] === null ? null : (int) $variant['member_price'];
                    foreach (['price', 'cost', 'discount_amount'] as $field) {
                        $variant[$field] = (int) $variant[$field];
                    }
                }
                unset($variant);
                $this->products->handle($actor, null, $data);
            }

            return count($groups);
        }, attempts: 3);
    }
}
