<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Users\UpdateUserAccess;
use App\Domains\Shared\Enums\SystemRole;
use App\Domains\Shared\Enums\UserStatus;
use App\Domains\Shared\Services\ListFilters;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserAccessRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class UserAccessController extends Controller
{
    public function index(Request $request, ListFilters $lists): Response
    {
        Gate::authorize('viewAny', User::class);

        return Inertia::render('admin/users/index', [
            'listFilters' => $lists->values($request),
            'users' => $lists->apply($request, User::query()->select(['id', 'name', 'email', 'status'])->with('roles:id,name'), ['name', 'email'], ['roles' => ['name']], ['active', 'suspended'])
                ->orderBy('id')->paginate(20)->withQueryString()->through(fn (User $user): array => [
                    'id' => $user->id, 'name' => $user->name, 'email' => $user->email,
                    'status' => $user->status->value, 'roles' => $user->getRoleNames()->values()->all(),
                    'canUpdate' => Gate::allows('updateAccess', $user),
                ]),
            'availableRoles' => array_map(fn (SystemRole $role): string => $role->value, SystemRole::cases()),
        ]);
    }

    public function store(StoreUserRequest $request, CreateNewUser $creator, UpdateUserAccess $access): RedirectResponse
    {
        /** @var array{name: string, email: string, password: string, password_confirmation: string, roles: list<string>, idempotency_key: string} $data */
        $data = $request->validated();
        $user = DB::transaction(function () use ($request, $creator, $access, $data): User {
            $user = $creator->create([
                'name' => $data['name'], 'email' => $data['email'],
                'password' => $data['password'], 'password_confirmation' => $data['password_confirmation'],
            ]);
            $access->handle($request->user(), $user, array_map(SystemRole::from(...), $data['roles']), UserStatus::Active, $data['idempotency_key']);

            return $user;
        });
        try {
            event(new Registered($user));
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengguna ditambahkan. Pemilik akun perlu memverifikasi email.']);
        } catch (Throwable $exception) {
            report($exception);
            Inertia::flash('toast', ['type' => 'warning', 'message' => 'Pengguna tersimpan. Email verifikasi belum terkirim; pemilik akun dapat mengirim ulang setelah login.']);
        }

        return to_route('admin.users.index');
    }

    public function update(UpdateUserAccessRequest $request, User $user, UpdateUserAccess $updateAccess): RedirectResponse
    {
        /** @var array{roles: list<string>, status: string, idempotency_key: string} $data */
        $data = $request->validated();
        $updateAccess->handle($request->user(), $user, array_map(SystemRole::from(...), $data['roles']), UserStatus::from($data['status']), $data['idempotency_key']);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Akses pengguna diperbarui.']);

        return to_route('admin.users.index');
    }
}
