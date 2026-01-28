<?php

namespace App\Http\Requests\Ads;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'title'         => 'required|array',
            'title.en'      => 'required|string|max:255',
            'title.ar'      => 'required|string|max:255',
            'description'   => 'nullable|array',
            'images'   => 'nullable|array|max:5',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp',
        ];

        switch ($this->ad_type) {
            case 'service':
                $rules = array_merge($rules, [
                    'category_id'      => 'required|exists:categories,id',
                    'price'            => 'required|numeric|min:0',
                    'duration_minutes' => 'nullable|integer|min:5',
                    'max_concurrent_bookings' => 'nullable|integer|min:1',
                    'slot_interval_minutes' => 'nullable|integer|min:5',
                    'cancel_cutoff_hours' => 'nullable|integer|min:0',
                    'edit_cutoff_hours' => 'nullable|integer|min:0',
                    'location_type' => 'nullable|string|max:32',
                    'is_active' => 'nullable|boolean',

                ]);
                break;
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'title.required'           => __('ads.title_required'),
            'title.en.required'        => __('ads.title_en_required'),
            'title.ar.required'        => __('ads.title_ar_required'),


            // Product
            'category_id.required'     => __('ads.category_required'),
            'category_id.exists'       => __('ads.category_not_found'),

            'price.required'           => __('ads.price_required'),
            'price.numeric'            => __('ads.price_numeric'),


            // Images
            'images.array'             => __('ads.images_array'),
            'images.max'               => __('ads.images_max'),
            'images.*.image'           => __('ads.images_must_be_image'),
            'images.*.mimes'           => __('ads.images_mimes'),
            'images.*.max'             => __('ads.images_size_max'),
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => 0,
            'message' => __('ads.validation_failed'),
            'result' => ['errors' => $validator->errors()],
        ], 422));
    }
}
