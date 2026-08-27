<?php

/*
 * PHP Fiber Framework
 * https://github.com/php-puff/routing
 * https://github.com/php-puff/routing/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

namespace Puff\Routing\Exception;

use Puff\Http\Exception\HttpException;

final class MethodNotAllowedException extends HttpException
{
    /** @param list<string> $allowed */
    public function __construct(
        string $message = 'Method Not Allowed',
        private readonly array $allowed = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 405, $previous);
    }

    /** @param list<string> $allowed */
    public static function for(string $method, array $allowed, ?string $path = null): self
    {
        $allowed = \array_values(\array_unique(\array_map(\strtoupper(...), $allowed)));
        $target = $path === null ? '' : " for [{$path}]";
        $suffix = $allowed === [] ? '' : '; allowed: ' . \implode(', ', $allowed);
        return new self('Request method [' . \strtoupper($method) . "] is not allowed{$target}{$suffix}.", $allowed);
    }

    /** @return list<string> */
    public function allowed(): array
    {
        return $this->allowed;
    }
}
