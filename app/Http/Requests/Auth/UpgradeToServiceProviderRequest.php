<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpgradeToServiceProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'name'        => 'required|string|max:255',
            'address'     => 'required|string',
            'logo'        => 'nullable|image',
            'description' => 'nullable|string',

            'email' => [
                Rule::requiredIf(fn() => $this->user()?->email === null),
                'sometimes',
                'email',
                Rule::unique('users', 'email')->ignore($userId),
            ],

            'phone' => [
                Rule::requiredIf(fn() => $this->user()?->phone === null),
                'string',
                Rule::unique('users', 'phone')->ignore($userId),
            ],

            'attachments'   => 'nullable|array',
            'attachments.*' => 'file',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'        => __('auth.name_required'),
            'address.required'     => __('auth.address_required'),
            'logo.image'           => __('auth.logo_must_be_image'),

            'email.required' => __('this_user_should_add_email'),
            'phone.required' => __('this_user_should_add_phone'),

            'phone.unique'   => __('auth.phone_unique'),
            'email.unique'   => __('auth.email_unique'),
            'email.email'    => __('auth.email_invalid'),

            'attachments.array'  => __('auth.attachments_array'),
            'attachments.*.file' => __('auth.attachments_file'),
            'attachments.*.max'  => __('auth.attachments_file_max'),
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => 0,
            'message' => __('auth.validation_failed'),
            'result'  => ['errors' => $validator->errors()],
        ], 422));
    }
}
