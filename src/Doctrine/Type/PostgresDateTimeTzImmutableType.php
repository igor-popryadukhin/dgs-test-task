<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateTimeTzImmutableType;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;

final class PostgresDateTimeTzImmutableType extends DateTimeTzImmutableType
{
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?\DateTimeImmutable
    {
        if (null === $value || $value instanceof \DateTimeImmutable) {
            return $value;
        }
        if (!is_string($value)) {
            throw InvalidType::new($value, self::class, ['null', 'string', \DateTimeImmutable::class]);
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            throw InvalidFormat::new($value, self::class, 'a valid PostgreSQL timestamptz value');
        }
    }
}
