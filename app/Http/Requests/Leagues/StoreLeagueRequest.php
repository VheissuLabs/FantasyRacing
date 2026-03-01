<?php

namespace App\Http\Requests\Leagues;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeagueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'franchise_id' => ['required', 'integer', 'exists:franchises,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'max_teams' => ['nullable', 'integer', 'min:2'],
            'visibility' => ['required', 'in:public,private'],
            'join_policy' => ['required', 'in:open,request,invite_only'],
            'rules.no_duplicates' => ['boolean'],
            'rules.trade_approval_required' => ['boolean'],
            'rules.trades_enabled' => ['boolean'],
            'rules.max_roster_size' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
