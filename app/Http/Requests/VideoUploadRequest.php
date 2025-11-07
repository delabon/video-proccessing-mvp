<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class VideoUploadRequest extends FormRequest
{
    private const int MAX_SIZE = 10240; // 10mb
    private const int MIN_SIZE = 1; // 1kb

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
        return [
            'video' => [
                'required',
                'file',
                'mimetypes:video/mp4',
                'min:' . self::MIN_SIZE,
                'max:' . self::MAX_SIZE,
            ]
        ];
    }
}
