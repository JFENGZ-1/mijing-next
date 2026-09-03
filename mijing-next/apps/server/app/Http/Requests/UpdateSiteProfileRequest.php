<?php

namespace App\Http\Requests;

class UpdateSiteProfileRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'logoUrl' => ['sometimes', 'nullable', 'string', 'max:500'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'region' => ['sometimes', 'nullable', 'array'],
            'region.provinceCode' => ['nullable', 'string', 'max:12'],
            'region.provinceName' => ['nullable', 'string', 'max:60'],
            'region.cityCode' => ['nullable', 'string', 'max:12'],
            'region.cityName' => ['nullable', 'string', 'max:60'],
            'region.countyCode' => ['nullable', 'string', 'max:12'],
            'region.countyName' => ['nullable', 'string', 'max:60'],
            'businessHours' => ['sometimes', 'array', 'min:1'],
            'businessHours.*.weekDays' => ['required_with:businessHours', 'string', 'regex:/^[1-7]+$/'],
            'businessHours.*.timeValue' => ['required_with:businessHours', 'string', 'max:120'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'timezone' => ['sometimes', 'timezone:all'],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }
}
