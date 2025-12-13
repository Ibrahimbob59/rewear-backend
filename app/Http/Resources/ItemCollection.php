<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ItemCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'items' => ItemResource::collection($this->collection),
        ];
    }
}
