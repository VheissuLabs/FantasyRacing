<?php

namespace App\Http\Requests\Leagues;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class DraftScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'scheduled_at' => ['required', 'date', 'after:now'],
            'present_user_ids' => ['nullable', 'array'],
            'present_user_ids.*' => ['integer'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $timezone = $this->user()->timezone ?? 'UTC';
        $this->merge([
            'scheduled_at' => Carbon::parse($this->input('scheduled_at'), $timezone)->utc()->toDateTimeString(),
        ]);
    }
}
