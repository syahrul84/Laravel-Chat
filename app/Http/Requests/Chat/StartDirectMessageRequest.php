<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartDirectMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id', Rule::notIn([$this->user()->id])],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.not_in' => 'You cannot message yourself.',
            'user_id.exists' => 'That user does not exist.',
        ];
    }
}
