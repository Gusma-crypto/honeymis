<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateMlmProgramRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('commission-rules.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'referral_first_order_bps' => ['required', 'integer', 'between:0,10000'],
            'generation_rates' => ['required', 'array', 'list', 'size:5'],
            'generation_rates.*' => ['required', 'integer', 'between:0,10000'],
            'rank_requirements' => ['required', 'array:Member,Bronze,Silver,Gold,Platinum'],
            'rank_requirements.Member' => ['required', 'array:personal_sales,team_sales'],
            'rank_requirements.Member.personal_sales' => ['required', 'integer', 'in:0'],
            'rank_requirements.Member.team_sales' => ['required', 'integer', 'in:0'],
            'rank_requirements.Bronze' => ['required', 'array:personal_sales,team_sales'],
            'rank_requirements.Bronze.personal_sales' => ['required', 'integer', 'min:0'],
            'rank_requirements.Bronze.team_sales' => ['required', 'integer', 'min:0'],
            'rank_requirements.Silver' => ['required', 'array:personal_sales,team_sales'],
            'rank_requirements.Silver.personal_sales' => ['required', 'integer', 'min:0'],
            'rank_requirements.Silver.team_sales' => ['required', 'integer', 'min:0'],
            'rank_requirements.Gold' => ['required', 'array:personal_sales,team_sales'],
            'rank_requirements.Gold.personal_sales' => ['required', 'integer', 'min:0'],
            'rank_requirements.Gold.team_sales' => ['required', 'integer', 'min:0'],
            'rank_requirements.Platinum' => ['required', 'array:personal_sales,team_sales'],
            'rank_requirements.Platinum.personal_sales' => ['required', 'integer', 'min:0'],
            'rank_requirements.Platinum.team_sales' => ['required', 'integer', 'min:0'],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $requirements = $this->input('rank_requirements');
            $previous = $requirements['Member'];
            foreach (['Bronze', 'Silver', 'Gold', 'Platinum'] as $rank) {
                foreach (['personal_sales', 'team_sales'] as $metric) {
                    if ($requirements[$rank][$metric] < $previous[$metric]) {
                        $validator->errors()->add("rank_requirements.{$rank}.{$metric}", 'Target harus sama dengan atau lebih tinggi dari rank sebelumnya.');
                    }
                }
                $previous = $requirements[$rank];
            }
        }];
    }
}
