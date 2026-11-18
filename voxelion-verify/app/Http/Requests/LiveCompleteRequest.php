<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LiveCompleteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'capture' => ['required', 'file', 'mimetypes:video/webm,video/mp4,image/jpeg,image/png', 'max:51200'],
            // Accessibility fallback — a still frame instead of a video
            // challenge routes the session to manual review.
            'still' => ['nullable', 'boolean'],
        ];
    }
}
