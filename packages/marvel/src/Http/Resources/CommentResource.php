<?php

namespace Marvel\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'user' => [
                'id' => $this->user->id ?? null,
                'name' => $this->user->name ?? 'Аноним',
                'avatar' => $this->user->avatar ?? null,
            ],
            'replies' => CommentResource::collection($this->whenLoaded('replies')),
            'parent_id' => $this->parent_id,
        ];
    }
}

