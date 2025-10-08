<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Rap2hpoutre\FastExcel\FastExcel;

class ContactsExport implements FromCollection, WithHeadings, WithMapping
{
    /** @var \Illuminate\Support\Collection */
    protected Collection $contacts;

    public function __construct(Collection $contacts)
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
            'id',
            'name',
            'company',
            'job_title',
            'email',
            'phone',
            'address',
            'notes',
            'tags',             // comma-separated tag names
            'linkedin_url',
            'website_url',
            'duplicate_of_id',
            'source',
            'created_at',
            'updated_at',
        ];
    }

    public function map($c): array
    {
        $tags = $c->relationLoaded('tags') ? $c->tags->pluck('name')->implode(',') : '';

        return [
            $c->id,
            $c->name,
            $c->company,
            $c->job_title,
            $c->email,
            $c->phone,
            $c->address,
            $c->notes,
            $tags,
            $c->linkedin_url,
            $c->website_url,
            $c->duplicate_of_id,
            $c->source,
            optional($c->created_at)->toDateTimeString(),
            optional($c->updated_at)->toDateTimeString(),
        ];
    }
}
