<?php

namespace App\Http\Controllers;

use App\Domains\Orders\Actions\SubmitPaymentProof;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\Payment;
use App\Http\Requests\Commerce\PaymentProofRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function store(PaymentProofRequest $request, Order $order, SubmitPaymentProof $proofs): RedirectResponse
    {
        $file = $request->file('proof');
        abort_unless($file instanceof UploadedFile, 422);
        $proofs->handle($request->user(), $order, $file, $request->string('idempotency_key')->toString());

        return to_route('orders.show', $order);
    }

    public function show(Payment $payment): StreamedResponse
    {
        Gate::authorize('viewProof', $payment);
        abort_if($payment->proof_storage_key === null, 404);

        return Storage::disk('local')->download($payment->proof_storage_key, 'bukti-pembayaran-'.$payment->id, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
