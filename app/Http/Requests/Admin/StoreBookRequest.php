<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class StoreBookRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('books', 'slug')],
            'price' => ['required', 'numeric', 'min:0'],
            'cover' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'cover_thumb' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'epub' => ['nullable', 'file', 'max:102400', function (string $attribute, mixed $value, \Closure $fail) {
                if (! $value instanceof UploadedFile) {
                    return;
                }
                if (strtolower($value->getClientOriginalExtension()) !== 'epub') {
                    $fail('Файл должен иметь расширение .epub.');

                    return;
                }
                $allowed = ['application/epub+zip', 'application/octet-stream'];
                if (! in_array($value->getMimeType(), $allowed, true)) {
                    $fail('Файл должен быть в формате epub.');
                }
            }],
            'annotation' => ['nullable', 'string', 'max:5000'],
            'excerpt' => ['nullable', 'string', 'max:10000'],
            'fragment' => ['nullable', 'string', 'max:100000'],
            'is_featured' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Введите название книги.',
            'title.max' => 'Название не должно превышать 255 символов.',
            'slug.required' => 'Введите URL-адрес книги.',
            'slug.alpha_dash' => 'URL-адрес может содержать только буквы, цифры, дефисы и подчёркивания.',
            'slug.unique' => 'Книга с таким URL-адресом уже существует.',
            'slug.max' => 'URL-адрес не должен превышать 255 символов.',
            'price.required' => 'Введите цену книги.',
            'price.numeric' => 'Цена должна быть числом.',
            'price.min' => 'Цена не может быть отрицательной.',
            'cover.file' => 'Обложка должна быть файлом.',
            'cover.mimes' => 'Обложка должна быть изображением в формате JPG, PNG или WebP.',
            'cover.max' => 'Размер обложки не должен превышать 5 МБ.',
            'cover_thumb.file' => 'Миниатюра должна быть файлом.',
            'cover_thumb.mimes' => 'Миниатюра должна быть изображением в формате JPG, PNG или WebP.',
            'cover_thumb.max' => 'Размер миниатюры не должен превышать 2 МБ.',
            'epub.file' => 'Файл книги должен быть файлом.',
            'epub.max' => 'Размер epub-файла не должен превышать 100 МБ.',
            'annotation.max' => 'Аннотация не должна превышать 5000 символов.',
            'excerpt.max' => 'Отрывок не должен превышать 10000 символов.',
            'fragment.max' => 'Фрагмент не должен превышать 100000 символов.',
            'sort_order.integer' => 'Порядок сортировки должен быть целым числом.',
            'sort_order.min' => 'Порядок сортировки не может быть отрицательным.',
        ];
    }
}
