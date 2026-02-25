<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class CreateChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'unique:channels,name'],
            'type' => ['sometimes', 'in:public,private'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
