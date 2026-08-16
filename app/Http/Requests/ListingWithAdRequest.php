<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListingWithAdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    protected function prepareForValidation(): void
    {
    }

    public function rules(): array
    {
        return [
            // Listing
            'title'        => ['required', 'string', 'max:255'],
            'description'  => ['nullable', 'string'],

            'images'       => ['required', 'array', 'max:12'],
            'images.*'     => ['file', 'image', 'max:4096'],

            // Ad (placement)
            'ad.title'          => ['required', 'string', 'max:255'],
            'ad.subtitle'       => ['nullable', 'string'],
            'ad.ad_position_id' => ['nullable', 'exists:ad_positions,id'],
            // If you only support explicit expiry date (no days):
            'ad.expires_at'     => ['nullable', 'date', 'after:now'],

            // Optional: provide separate creatives for the ad (if omitted, we’ll reuse listing images)
            'ad.images'         => ['nullable', 'array', 'max:5'],
            'ad.images.*'       => ['file', 'image', 'max:4096'],
        ];
    }
}
