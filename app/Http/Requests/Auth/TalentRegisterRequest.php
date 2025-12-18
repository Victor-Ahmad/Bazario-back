<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ServiceProviderRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'name'             => 'required|string|max:255',
            'address'          => 'required|string',
            'logo'             => 'nullable|image',
            'description'      => 'nullable|string',
            'phone'            => 'required|string|max:20|unique:users,phone',
            'email'            => 'required|email|unique:users,email',
            'password'         => 'required|string|min:6|confirmed',
        ];
    }


    public function messages(): array
    {
        return [
            'name.required' => __('auth.name_required'),
            'address.required' => __('auth.address_required'),
            'logo.image' => __('auth.logo_must_be_image'),
            'description.required' => __('auth.description_required'),
            'phone.required' => __('auth.phone_required'),
            'email.required' => __('auth.email_required'),
            'phone.unique'   => __('auth.phone_unique'),
            'email.unique'   => __('auth.email_unique'),
            'email.email'    => __('auth.email_invalid'),
            'password.required'  => __('auth.password_required'),
            'password.confirmed' => __('auth.password_mismatch')
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
