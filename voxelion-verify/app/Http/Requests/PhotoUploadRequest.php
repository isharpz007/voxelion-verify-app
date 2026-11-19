<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the selfie upload — type, size and extension limits enforced server-side.
 */
class PhotoUploadRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Direct multipart upload (local/dev media disk).
            'photo' => ['required_without:storage_key', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
            // Key issued by POST /photo/upload-url after the browser PUTs
            // straight to the media disk (S3 presigned flow in prod).
            'storage_key' => ['required_without:photo', 'string', 'max:255'],
        ];
    }
}
