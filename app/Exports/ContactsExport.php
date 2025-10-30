<?php

namespace App\Exports;

use App\Models\Contact;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ContactsExport implements FromCollection, WithHeadings, WithMapping
{
    protected $contacts;

    public function __construct($contacts)
    {
        $this->contacts = $contacts;
    }

    public function collection()
    {
        return $this->contacts;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'Company',
            'Job Title',
            'Email',
            'Phone',
            'Address Detail',
            'City',
            'State/Province',
            'Country',
            'Notes',
            'LinkedIn URL',
            'Website URL',
            'Tags',
            'Source',
            'Created At',
        ];
    }

    public function map($contact): array
    {
        return [
            $contact->id,
            $contact->name,
            $contact->company,
            $contact->job_title,
            $contact->email,
            $contact->phone,
            $contact->address->address_detail ?? '',
            $contact->address->city->name ?? '',
            $contact->address->state->name ?? '',
            $contact->address->country->name ?? '',
            $contact->notes,
            $contact->linkedin_url,
            $contact->website_url,
            $contact->tags->pluck('name')->map(fn($n) => "#{$n}")->join(', '),
            $contact->source,
            $contact->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
