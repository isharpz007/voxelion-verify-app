<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the two consent checkboxes — both must be explicitly agreed before any data is collected.
 */
class ConsentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'agree_processing' => ['accepted'],
            'agree_biometrics' => ['accepted'],
        ];
    }
}
