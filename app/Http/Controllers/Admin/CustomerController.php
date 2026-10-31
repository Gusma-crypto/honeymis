<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Orders\Actions\SaveCustomer;
use App\Domains\Orders\Models\Customer;
use App\Domains\Orders\Models\Order;
use App\Domains\Shared\Services\ListFilters;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CustomerContactRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request, ListFilters $lists): Response
    {
        Gate::authorize('viewAny', Customer::class);
        $query = $lists->apply($request, Customer::query(), ['name', 'email', 'phone'], [], ['linked', 'guest'], null);
        if ($request->input('status') === 'linked') {
            $query->whereNotNull('user_id');
        } elseif ($request->input('status') === 'guest') {
            $query->whereNull('user_id');
        }

        return Inertia::render('admin/customers', ['listFilters' => $lists->values($request), 'customers' => $query->orderByDesc('id')->paginate(20)->withQueryString(), 'search' => $request->string('search')->toString()]);
    }

    public function show(Customer $customer): Response
    {
        Gate::authorize('view', $customer);

        return Inertia::render('admin/customer', ['customer' => $customer->only(['id', 'name', 'email', 'phone']), 'orders' => Order::where('customer_id', $customer->id)->orderByDesc('id')->paginate(20)->through(fn (Order $order): array => $order->only(['id', 'order_number', 'status', 'grand_total']))]);
    }

    public function store(CustomerContactRequest $request, SaveCustomer $customers): RedirectResponse
    {
        $customers->handle($request->user(), null, ['name' => $request->string('name')->toString(), 'email' => $request->string('email')->toString(), 'phone' => $request->string('phone')->toString() ?: null], $request->string('idempotency_key')->toString());

        return to_route('admin.customers.index');
    }

    public function update(CustomerContactRequest $request, Customer $customer, SaveCustomer $customers): RedirectResponse
    {
        $customers->handle($request->user(), $customer, ['name' => $request->string('name')->toString(), 'email' => $request->string('email')->toString(), 'phone' => $request->string('phone')->toString() ?: null], $request->string('idempotency_key')->toString());

        return to_route('admin.customers.index');
    }
}
