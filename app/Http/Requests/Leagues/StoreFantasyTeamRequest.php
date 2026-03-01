<?php

namespace App\Http\Requests\Leagues;

use Illuminate\Foundation\Http\FormRequest;

class StoreFantasyTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        $league = $this->route('league');

        return $league->members()->where('user_id', $this->user()->id)->exists()
            && ! $league->fantasyTeams()->where('user_id', $this->user()->id)->exists();
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
