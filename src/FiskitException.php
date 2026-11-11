<?php

declare(strict_types=1);

namespace Fiskit;

/** An error answered by the API, or a network failure (status 0). */
final class FiskitException extends \RuntimeException
{
    /**
     * @param mixed $body the decoded error response, if any
     */
    public function __construct(
        public readonly int $status,
        public readonly string $type,
        public readonly string $errorCode,
        string $message,
        public readonly ?string $param = null,
        public readonly ?string $requestId = null,
        public readonly mixed $body = null,
    ) {
        parent::__construct($message, $status);
    }

    public static function fromResponse(int $status, string $body, ?string $requestId): self
    {
        $payload = json_decode($body, true);
        $error = is_array($payload) && is_array($payload['error'] ?? null) ? $payload['error'] : [];
        return new self(
            $status,
            $error['type'] ?? 'api_error',
            $error['code'] ?? 'http_' . $status,
            $error['message'] ?? 'HTTP ' . $status,
            $error['param'] ?? null,
            $error['request_id'] ?? $requestId,
            $payload,
        );
    }
}
