<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'role' => 'nullable|string',
            'telegram_username' => 'nullable|string',
            'nickname' => 'nullable|string',
            'avatar' => 'nullable|string',
            'banner' => 'nullable|string',
            'bio'=>'nullable|string'
        ];

        if ($this->method() === 'POST') {
            $rules['email'] = 'required|string|email|max:255|unique:users';
            $rules['password'] = 'required|string|min:8';
        }

        return $rules;
    }
}
