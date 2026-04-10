<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ContactResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'company'   => $this->company,
            'job_title' => $this->job_title,
            'email'     => $this->email,
            'phone'     => $this->phone,
            'address'   => $this->address,
            'notes'     => $this->notes,
            'linkedin_url' => $this->linkedin_url,
            'website_url'  => $this->website_url,
            'source'       => $this->source,
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,

            'avatar'           => $this->avatar_url,
            'avatar_url'       => $this->avatar_url,
            'card_image_front' => $this->card_front_url,
            'card_image_back'  => $this->card_back_url,
            'card_front_url'   => $this->card_front_url,
            'card_back_url'    => $this->card_back_url,

            'tags' => TagResource::collection($this->whenLoaded('tags')),
        ];
    }
}
