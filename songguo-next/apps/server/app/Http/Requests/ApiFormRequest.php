<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class ApiFormRequest extends FormRequest
{
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'code' => 'VALIDATION_FAILED',
            'message' => '请求参数不符合要求',
            'details' => $validator->errors(),
            'requestId' => request()->attributes->get('request_id'),
        ], 422));
    }
}
