<?php

namespace App\Http\Requests\Ads;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name'         => 'required|array',
            'name.en'      => 'required|string|max:255',
            'name.ar'      => 'required|string|max:255',
            'description'  => 'nullable|array',
            'category_id'  => 'required|exists:categories,id',
            'price'        => 'required|numeric|min:0',
            'images'       => 'nullable|array|max:5',
            'images.*'     => 'image|mimes:jpg,jpeg,png,webp|max:4096',
        ];

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.required'           => __('ads.name_required'),
            'name.en.required'        => __('ads.name_en_required'),
            'name.ar.required'        => __('ads.name_ar_required'),


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
