<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePartnerRankRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('partners.manage') === true && $this->user()->can('commission-rules.manage');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rank' => ['present', 'nullable', 'in:Member,Bronze,Silver,Gold,Platinum'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'rank.present' => 'Pilih tindakan peringkat.',
            'rank.in' => 'Peringkat yang dipilih tidak valid.',
            'reason.required' => 'Alasan perubahan peringkat wajib diisi.',
            'reason.string' => 'Alasan harus berupa teks.',
            'reason.min' => 'Alasan minimal 10 karakter.',
            'reason.max' => 'Alasan maksimal 2000 karakter.',
        ];
    }
}
