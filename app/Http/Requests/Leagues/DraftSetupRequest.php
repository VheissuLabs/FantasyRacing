<?php

namespace App\Http\Requests\Leagues;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class DraftSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:snake,linear'],
            'pick_time_limit_seconds' => ['required', 'integer', 'min:10'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'present_user_ids' => ['nullable', 'array'],
            'present_user_ids.*' => ['integer'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('scheduled_at')) {
            $timezone = $this->user()->timezone ?? 'UTC';
            $this->merge([
                'scheduled_at' => Carbon::parse($this->input('scheduled_at'), $timezone)->utc()->toDateTimeString(),
            ]);
        }
    }
}
