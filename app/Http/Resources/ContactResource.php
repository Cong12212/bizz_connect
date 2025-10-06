<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'      => $this->id,
            'name'    => $this->name,
            'company' => $this->company,
            'email'   => $this->email,
            'phone'   => $this->phone,
            'address' => $this->address,
            'notes'   => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'tags' => TagResource::collection($this->whenLoaded('tags')),
        ];
    }
}
