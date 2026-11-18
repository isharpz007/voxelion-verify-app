<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
