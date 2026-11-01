<?php

namespace App\Domains\Orders\Actions;

use App\Domains\Orders\Models\Order;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ImportTrackingNumbers
{
    public function __construct(private ManageOrder $orders) {}

    public function handle(User $actor, UploadedFile $file): int
    {
        abort_unless($actor->can('orders.manage'), 403);
        $stream = fopen($file->getPathname(), 'r');
        if ($stream === false) {
            throw ValidationException::withMessages(['file' => 'File resi tidak dapat dibaca.']);
        }
        $rows = [];
        try {
            $header = fgetcsv($stream, escape: '');
            if ($header !== false && isset($header[0])) {
                $header[0] = ltrim((string) $header[0], "\xEF\xBB\xBF");
            }
            if ($header !== ['order_number', 'courier', 'tracking_number']) {
                throw ValidationException::withMessages(['file' => 'Gunakan kolom order_number,courier,tracking_number sesuai template.']);
            }
            while (($row = fgetcsv($stream, escape: '')) !== false) {
                if ($row === [null]) {
                    continue;
                }
                if (count($row) !== 3 || count($rows) >= 100) {
                    throw ValidationException::withMessages(['file' => 'Maksimal 100 baris dengan 3 kolom sesuai template.']);
                }
                $data = array_combine($header, array_map(static fn ($value): string => trim((string) $value), $row));
                $validator = Validator::make($data, ['order_number' => ['required', 'string', 'max:100'], 'courier' => ['required', 'string', 'max:100'], 'tracking_number' => ['required', 'string', 'max:100']]);
                if ($validator->fails() || isset($rows[$data['order_number']])) {
                    throw ValidationException::withMessages(['file' => 'Baris resi tidak valid atau nomor pesanan berulang.']);
                }
                $rows[$data['order_number']] = $data;
            }
        } finally {
            fclose($stream);
        }
        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'File resi belum berisi data.']);
        }
        ksort($rows);

        return DB::transaction(function () use ($actor, $rows): int {
            foreach ($rows as $row) {
                $order = Order::where('order_number', $row['order_number'])->first();
                if ($order === null) {
                    throw ValidationException::withMessages(['file' => 'Pesanan '.$row['order_number'].' tidak ditemukan.']);
                }
                if (! in_array($order->status, ['processing', 'shipped'], true)) {
                    throw ValidationException::withMessages(['file' => 'Pesanan '.$row['order_number'].' harus berstatus Diproses sebelum diimpor.']);
                }
                $key = 'tracking-import:'.hash('sha256', json_encode([$order->id, $row['courier'], $row['tracking_number']], JSON_THROW_ON_ERROR));
                try {
                    $this->orders->handle($actor, $order, 'ship', $key, ['courier' => $row['courier'], 'tracking_number' => $row['tracking_number']]);
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages(['file' => $row['order_number'].': '.$exception->validator->errors()->first()]);
                }
            }

            return count($rows);
        }, attempts: 3);
    }
}
