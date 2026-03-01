<?php

namespace App\Http\Requests\Leagues;

use Illuminate\Foundation\Http\FormRequest;

class StoreTradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'receiver_team_id' => ['nullable', 'integer', 'exists:fantasy_teams,id'],
            'giving' => ['required', 'array', 'min:1'],
            'giving.*.entity_type' => ['required', 'in:driver,constructor'],
            'giving.*.entity_id' => ['required', 'integer', 'min:1'],
            'receiving' => ['required', 'array', 'min:1'],
            'receiving.*.entity_type' => ['required', 'in:driver,constructor'],
            'receiving.*.entity_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
