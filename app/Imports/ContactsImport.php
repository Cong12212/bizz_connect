<?php

namespace App\Imports;

use App\Models\Contact;
use App\Models\Address;
use App\Models\Tag;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ContactsImport implements ToCollection, WithHeadingRow
{
    protected $userId;
    protected $matchBy;
    protected $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

    public function __construct(int $userId, string $matchBy = 'id')
    {
        $this->userId = $userId;
        $this->matchBy = $matchBy;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            try {
                $this->processRow($row, $index + 2); // +2 vì header row và zero-indexed
            } catch (\Exception $e) {
                $this->stats['errors'][] = "Row " . ($index + 2) . ": " . $e->getMessage();
                $this->stats['skipped']++;
            }
        }
    }

    protected function processRow($row, $rowNumber)
    {
        // Validate required fields
        $name = trim($row['name'] ?? $row['name_'] ?? '');
        if (!$name) {
            throw new \Exception("Name is required");
        }

        // Prepare contact data
        $contactData = [
            'owner_user_id' => $this->userId,
            'name' => $name,
            'company' => $row['company'] ?? null,
            'job_title' => $row['job_title'] ?? null,
            'email' => $row['email'] ?? null,
            'phone' => $row['phone'] ?? null,
            'notes' => $row['notes'] ?? null,
            'linkedin_url' => $row['linkedin_url'] ?? null,
            'website_url' => $row['website_url'] ?? null,
            'source' => $row['source'] ?? 'import',
        ];

        // Xử lý address
        $addressId = null;
        $addressDetail = $row['address_detail'] ?? null;
        $cityCode = $row['city'] ?? $row['city_code'] ?? null;
        $stateCode = $row['state'] ?? $row['state_code'] ?? null;
        $countryCode = $row['country'] ?? $row['country_code'] ?? null;

        if ($addressDetail || $cityCode) {
            // Lấy ID từ code
            $cityId = $cityCode ? DB::table('cities')->where('code', $cityCode)->value('id') : null;
            $stateId = $stateCode ? DB::table('states')->where('code', $stateCode)->value('id') : null;
            $countryId = $countryCode ? DB::table('countries')->where('code', $countryCode)->value('id') : null;

            $address = Address::create([
                'address_detail' => $addressDetail,
                'city_id' => $cityId,
                'state_id' => $stateId,
                'country_id' => $countryId,
            ]);
            $addressId = $address->id;
        }

        $contactData['address_id'] = $addressId;

        // Find existing contact
        $existing = null;
        if ($this->matchBy === 'email' && !empty($contactData['email'])) {
            $existing = Contact::where('owner_user_id', $this->userId)
                ->where('email', $contactData['email'])
                ->first();
        } elseif ($this->matchBy === 'phone' && !empty($contactData['phone'])) {
            $existing = Contact::where('owner_user_id', $this->userId)
                ->where('phone', $contactData['phone'])
                ->first();
        } elseif ($this->matchBy === 'id' && !empty($row['id'])) {
            $existing = Contact::where('owner_user_id', $this->userId)
                ->where('id', $row['id'])
                ->first();
        }

        // Create or update
        if ($existing) {
            $existing->update($contactData);
            $contact = $existing;
            $this->stats['updated']++;
        } else {
            $contact = Contact::create($contactData);
            $this->stats['created']++;
        }

        // Process tags
        $tagsString = $row['tags'] ?? '';
        if ($tagsString) {
            $tagNames = array_map('trim', explode(',', $tagsString));
            $tagIds = [];

            foreach ($tagNames as $tagName) {
                $tagName = ltrim($tagName, '#');
                if ($tagName) {
                    $tag = Tag::firstOrCreate(
                        ['owner_user_id' => $this->userId, 'name' => $tagName]
                    );
                    $tagIds[] = $tag->id;
                }
            }

            if ($tagIds) {
                $contact->tags()->sync($tagIds);
            }
        }
    }

    public function result()
    {
        return $this->stats;
    }
}
