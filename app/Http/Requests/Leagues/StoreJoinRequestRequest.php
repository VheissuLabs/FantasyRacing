<?php

namespace App\Http\Requests\Leagues;

use Illuminate\Foundation\Http\FormRequest;

class StoreJoinRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
