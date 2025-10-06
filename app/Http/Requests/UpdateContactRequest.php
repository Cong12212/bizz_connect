<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContactRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'       => 'sometimes|required|string|max:255',
            'company'    => 'nullable|string|max:255',
            'email'      => 'nullable|email|max:255',
            'phone'      => 'nullable|string|max:50',
            'address'    => 'nullable|string|max:255',
            'notes'      => 'nullable|string',

            // tags (tuỳ chọn)
            'tag_ids'    => 'nullable|array',
            'tag_ids.*'  => 'integer|exists:tags,id',
            'tag_names'  => 'nullable|array',
            'tag_names.*'=> 'string|max:100',
            // nếu muốn sync toàn bộ thay vì chỉ attach
            'sync_tags'  => 'nullable|boolean',
        ];
    }
}
