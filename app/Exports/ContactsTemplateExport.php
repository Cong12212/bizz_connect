<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ContactsTemplateExport implements FromArray, WithHeadings
{
    /** Không có data – chỉ cần header */
    public function array(): array
    {
        return [];
    }

    public function headings(): array
    {
        return [
            'id',
            'name',
            'company',
            'job_title',
            'email',
            'phone',
            'address',
            'notes',
            'tags',             // tên tag ngăn cách bằng dấu phẩy
            'linkedin_url',
            'website_url',
            'duplicate_of_id',
            'source',
            'created_at',
            'updated_at',
        ];
    }
}
