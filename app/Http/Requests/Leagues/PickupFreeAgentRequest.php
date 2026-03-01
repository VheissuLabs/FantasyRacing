<?php

namespace App\Http\Requests\Leagues;

use Illuminate\Foundation\Http\FormRequest;

class PickupFreeAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'entity_type' => ['required', 'in:driver,constructor'],
            'pickup_entity_id' => ['required', 'integer', 'min:1'],
            'drop_entity_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
