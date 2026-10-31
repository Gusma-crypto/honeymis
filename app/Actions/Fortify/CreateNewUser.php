<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Domains\Shared\Enums\SystemRole;
use App\Domains\Shared\ValueObjects\WhatsAppNumber;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Spatie\Permission\Models\Role;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, mixed>  $input  Untrusted registration fields; validated before persistence.
     */
    public function create(array $input): User
    {
        if (isset($input['whatsapp_number']) && is_string($input['whatsapp_number'])) {
            $input['whatsapp_number'] = WhatsAppNumber::normalize($input['whatsapp_number']) ?? $input['whatsapp_number'];
        }
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'whatsapp_number' => ['bail', 'nullable', 'string', 'max:15', 'regex:/^[1-9][0-9]{8,14}$/D', Rule::unique('users', 'whatsapp_number')],
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'whatsapp_number' => $input['whatsapp_number'] ?? null,
                'password' => $input['password'],
            ]);
            $user->assignRole(Role::findOrCreate(SystemRole::Customer->value, 'web'));
            DB::table('audit_logs')->insert([
                'actor_id' => null, 'action' => 'customer_registered', 'subject_type' => 'users', 'subject_id' => $user->id,
                'before_json' => null, 'after_json' => json_encode(['roles' => [SystemRole::Customer->value]], JSON_THROW_ON_ERROR),
                'idempotency_key' => 'registration:'.Str::uuid(), 'created_at' => now(),
            ]);

            return $user;
        });
    }
}
