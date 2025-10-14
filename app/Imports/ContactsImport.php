<?php

namespace App\Imports;

use App\Models\Contact;
use App\Models\Tag;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Concerns\OnEachRow;

class ContactsImport implements OnEachRow, WithHeadingRow
{
    protected int $ownerId;
    protected string $matchBy; // id|email|phone
    protected int $created = 0;
    protected int $updated = 0;
    protected int $skipped = 0;
    protected array $errors = [];

    public function __construct(int $ownerId, string $matchBy = 'id')
    {
        $this->ownerId = $ownerId;
        $this->matchBy = in_array($matchBy, ['id','email','phone']) ? $matchBy : 'id';
    }

    public function onRow(Row $row)
    {
        $data = $row->toArray(); // heading row => assoc

        // Normalize keys to snake_case (in case user changes column names)
        $data = collect($data)->keyBy(fn($v,$k)=>Str::snake(trim((string)$k)))->all();

        // Skip if missing name and no key to match
        $name = trim((string)($data['name'] ?? ''));
        $email = trim((string)($data['email'] ?? ''));
        $phone = trim((string)($data['phone'] ?? ''));
        $idVal = $data['id'] ?? null;

        if ($name === '' && empty($email) && empty($phone) && empty($idVal)) {
            $this->skipped++; return;
        }

        // Find record by matchBy strategy
        $contact = null;
        if ($this->matchBy === 'id' && $idVal) {
            $contact = Contact::where('owner_user_id', $this->ownerId)->find($idVal);
        } elseif ($this->matchBy === 'email' && $email) {
            $contact = Contact::where('owner_user_id', $this->ownerId)->where('email', $email)->first();
        } elseif ($this->matchBy === 'phone' && $phone) {
            $contact = Contact::where('owner_user_id', $this->ownerId)->where('phone', $phone)->first();
        }

        // Map valid fields
        $payload = Arr::only($data, [
            'name','company','job_title','email','phone','address','notes',
            'linkedin_url','website_url','ocr_raw','duplicate_of_id','search_text','source',
        ]);

        // Basic validation (name/email)
        if (!$contact && empty($payload['name'])) {
            // Create requires name
            $this->errors[] = ['row' => $row->getIndex(), 'error' => 'name is required for create'];
            $this->skipped++; return;
        }

        // Normalize
        $payload['source'] = $payload['source'] ?? 'manual';
        if (isset($payload['duplicate_of_id']) && $payload['duplicate_of_id'] !== null) {
            // Don't allow pointing to itself when matchBy = id and id matches
            if ($contact && (int)$payload['duplicate_of_id'] === (int)$contact->id) {
                $payload['duplicate_of_id'] = null;
            }
        }

        // Upsert
        if ($contact) {
            $contact->fill($payload)->save();
            $this->updated++;
        } else {
            $payload['owner_user_id'] = $this->ownerId;
            $contact = Contact::create($payload);
            $this->created++;
        }

        // Tags: "tags" column (comma-separated)
        if (!empty($data['tags'])) {
            $names = collect(explode(',', (string)$data['tags']))
                ->map(fn($s)=>trim($s))
                ->filter()
                ->map(fn($s)=>ltrim($s, '#'))
                ->unique()
                ->values();

            if ($names->isNotEmpty()) {
                $ids = [];
                foreach ($names as $nm) {
                    $tag = Tag::firstOrCreate(['name' => $nm]);
                    $ids[] = $tag->id;
                }
                $contact->tags()->syncWithoutDetaching($ids);
            }
        }
    }

    public function result(): array
    {
        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'errors'  => $this->errors,
        ];
    }
}
