<?php

namespace App\Http\Requests;

class JoinMemberSiteRequest extends ApiFormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return ['siteId' => ['required', 'integer', 'min:1']];
    }
}
