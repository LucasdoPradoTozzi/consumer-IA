<?php

namespace App\DTO;

use InvalidArgumentException;

class JobPayload
{
    public function __construct(
        public readonly string $jobId,
        public readonly string $type,
        public readonly array $data,
        public readonly ?string $callbackUrl = null,
        public readonly ?int $priority = null,
        public readonly ?array $metadata = null,
    ) {}

    /**
     * Create JobPayload from JSON string
     *
     * @param string $json
     * @return self
     * @throws InvalidArgumentException
     */
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }

        return self::fromArray($data);
    }

    /**
     * Create JobPayload from array
     *
     * @param array $data
     * @return self
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['job_id']) || !is_string($data['job_id'])) {
            throw new InvalidArgumentException('job_id is required and must be a string');
        }

        if (!isset($data['type']) || !is_string($data['type'])) {
            throw new InvalidArgumentException('type is required and must be a string');
        }

        if (!isset($data['data']) || !is_array($data['data'])) {
            throw new InvalidArgumentException('data is required and must be an array');
        }

        return new self(
            jobId: $data['job_id'],
            type: $data['type'],
            data: $data['data'],
            callbackUrl: $data['callback_url'] ?? null,
            priority: $data['priority'] ?? null,
            metadata: $data['metadata'] ?? null,
        );
    }

    /**
     * Convert to array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'job_id' => $this->jobId,
            'type' => $this->type,
            'data' => $this->data,
            'callback_url' => $this->callbackUrl,
            'priority' => $this->priority,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Convert to JSON
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }
}
