<?php

namespace App\Http\Requests\Leagues;

use Illuminate\Foundation\Http\FormRequest;

class StoreInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
        ];
    }
}
