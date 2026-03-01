<?php

namespace App\Http\Requests\Leagues;

use Illuminate\Foundation\Http\FormRequest;

class SwapRosterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'bench_driver_id' => ['required', 'integer', 'min:1'],
            'in_seat_driver_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
