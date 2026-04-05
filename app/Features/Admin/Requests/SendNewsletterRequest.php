<?php

declare(strict_types=1);

namespace App\Features\Admin\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendNewsletterRequest extends FormRequest
{
    /**
     * Admin middleware already enforces admin role — if we got here, we're authorized.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'confirm_send' => ['required', 'accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subject.required' => 'Введите тему рассылки.',
            'subject.max' => 'Тема не должна превышать 255 символов.',
            'body.required' => 'Введите текст рассылки.',
            'confirm_send.required' => 'Подтвердите отправку рассылки.',
            'confirm_send.accepted' => 'Подтвердите отправку рассылки.',
        ];
    }
}
