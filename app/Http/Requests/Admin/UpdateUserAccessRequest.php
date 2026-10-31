<?php

namespace App\Http\Requests\Admin;

use App\Domains\Shared\Enums\SystemRole;
use App\Domains\Shared\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subject = $this->route('user');

        return $subject instanceof User && ($this->user()?->can('updateAccess', $subject) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'roles' => ['required', 'array', 'min:1', 'max:5'],
            'roles.*' => ['required', 'string', 'distinct', Rule::enum(SystemRole::class)],
            'status' => ['required', Rule::enum(UserStatus::class)],
            'idempotency_key' => ['required', 'uuid'],
            'permissions' => ['prohibited'],
            'password' => ['prohibited'],
            'email' => ['prohibited'],
        ];
    }
}
