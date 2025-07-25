<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpgradeToTalentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:255',
            'address'     => 'required|string',
            'logo'        => 'nullable|image',
            'description' => 'nullable|string',
            // Attachments validation (multiple files)
            'attachments'   => 'nullable|array',
            'attachments.*' => 'file|max:10240', // up to 10MB per file
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'        => __('auth.name_required'),
            'address.required'     => __('auth.address_required'),
            'logo.image'           => __('auth.logo_must_be_image'),
            'description.required' => __('auth.description_required'),
            'attachments.array' => __('auth.attachments_array'),
            'attachments.*.file' => __('auth.attachments_file'),
            'attachments.*.max'  => __('auth.attachments_file_max'),
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => 0,
            'message' => __('auth.validation_failed'),
            'result' => ['errors' => $validator->errors()],
        ], 422));
    }
}
