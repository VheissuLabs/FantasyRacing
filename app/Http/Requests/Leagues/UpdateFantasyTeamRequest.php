<?php

namespace App\Http\Requests\Leagues;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateFantasyTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('team'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Your team needs a name.',
        ];
    }
}
