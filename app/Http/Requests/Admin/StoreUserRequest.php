<?php

namespace App\Http\Requests\Admin;

use App\Domains\Shared\Enums\SystemRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'], 'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed'], 'password_confirmation' => ['required', 'string'],
            'roles' => ['required', 'array', 'min:1', 'max:5'], 'roles.*' => ['required', 'string', 'distinct', Rule::enum(SystemRole::class)],
            'idempotency_key' => ['required', 'uuid'], 'permissions' => ['prohibited'], 'email_verified_at' => ['prohibited'], 'status' => ['prohibited'],
        ];
    }
}
