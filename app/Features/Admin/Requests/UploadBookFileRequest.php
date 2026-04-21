<?php

declare(strict_types=1);

namespace App\Features\Admin\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadBookFileRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:docx,epub,fb2', 'max:102400'],
            // Only derived (non-source) formats can be re-uploaded via this field.
            // Docx always goes through the source upload flow, never direct re-upload.
            'format' => ['nullable', 'string', 'in:epub,fb2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Выберите файл для загрузки.',
            'file.file' => 'Загружаемый объект должен быть файлом.',
            'file.mimes' => 'Файл должен быть в формате DOCX, EPUB или FB2.',
            'file.max' => 'Размер файла не должен превышать 100 МБ.',
            'format.in' => 'Формат для прямой загрузки должен быть epub или fb2.',
        ];
    }
}
