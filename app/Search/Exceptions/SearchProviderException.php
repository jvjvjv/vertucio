<?php

namespace App\Search\Exceptions;

use App\Search\Enums\SearchVertical;
use RuntimeException;

final class SearchProviderException extends RuntimeException
{
    public static function requestFailed(string $provider, SearchVertical $vertical, int $status): self
    {
        return new self("[{$provider}/{$vertical->value}] request failed with HTTP {$status}.");
    }

    public static function unexpectedPayload(string $provider, SearchVertical $vertical, string $reason): self
    {
        return new self("[{$provider}/{$vertical->value}] unexpected payload: {$reason}.");
    }

    public static function unsupportedVertical(string $provider, SearchVertical $vertical): self
    {
        return new self("[{$provider}] does not support the {$vertical->value} vertical.");
    }
}
