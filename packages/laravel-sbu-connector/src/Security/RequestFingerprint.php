<?php

declare(strict_types=1);

namespace WittyGekko\SbuConnector\Security;

final class RequestFingerprint
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function make(string $operation, array $parameters): string
    {
        return hash('sha256', $operation."\n".json_encode(self::canonicalize($parameters), JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    public static function canonicalize(array $parameters): array
    {
        ksort($parameters);
        foreach ($parameters as $key => $value) {
            if (is_array($value)) {
                $parameters[$key] = self::canonicalize($value);
            }
        }

        return $parameters;
    }
}
