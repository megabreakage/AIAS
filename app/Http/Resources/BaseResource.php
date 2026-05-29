<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class BaseResource extends JsonResource
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
     * Domain data ONLY
     */
    public function toArray(Request $request): array
    {
        return $this->resourceData($request);
    }

    /**
     * Top-level envelope (same idea as collections)
     */
    public function with(Request $request): array
    {
        return array_merge([
            'status' => $this->response_status,
            'message' => $this->message,
        ], $this->metadata);
    }

    abstract protected function resourceData(Request $request): array;
}
