<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class BaseResourceCollection extends ResourceCollection
{
    protected string $response_status = 'success';

    protected ?string $message = null;

    protected array $metadata = [];

    public function setStatus(string $status): self
    {
        $this->response_status = $status;

        return $this;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function addMetadata(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;

        return $this;
    }

    /**
     * Transform the resource into a JSON array.
     */
    public function toArray(Request $request): array
    {
        // Don't override toArray for paginated collections
        // Let Laravel handle the pagination structure
        return parent::toArray($request);
    }

    /**
     * Top-level response envelope.
     */
    public function with(Request $request): array
    {
        $response = [
            'status' => $this->response_status,
            'message' => $this->message,
        ];

        if (!empty($this->metadata)) {
            $response['metadata'] = $this->metadata;
        }

        return $response;
    }
}
