<?php

declare(strict_types=1);

namespace App\Features\Admin\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GrantBookRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'book_slug' => ['required', 'string', 'exists:books,slug'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
