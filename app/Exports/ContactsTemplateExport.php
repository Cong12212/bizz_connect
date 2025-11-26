<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ContactsTemplateExport implements FromArray, WithHeadings
{
    public function array(): array
    {
        return [
            [
                'John Doe',
                'ABC Company',
                'CEO',
                'john@example.com',
                '+84901234567',
                '123 Nguyễn Huệ, Phường Bến Nghé',
                'HCM',
                'SG',
                'VN',
                'Important client',
                'https://linkedin.com/in/johndoe',
                'https://example.com',
                '#client, #vip',
                'manual',
            ],
            [
                'Jane Smith',
                'XYZ Corp',
                'Marketing Director',
                'jane@example.com',
                '+84912345678',
                '456 Lê Lợi',
                'HAN',
                'HN',
                'VN',
                'Follow up next week',
                '',
                '',
                '#prospect',
                'manual',
            ],
        ];
    }

    public function headings(): array
    {
        return [
            'Name *',
            'Company',
            'Job Title',
            'Email',
            'Phone',
            'Address Detail',
            'City (code)',
            'State (code)',
            'Country (code)',
            'Notes',
            'LinkedIn URL',
            'Website URL',
            'Tags (comma-separated)',
            'Source',
        ];
    }
}
