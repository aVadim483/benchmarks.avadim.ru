<?php

declare(strict_types=1);

namespace App\Bench;

use Composer\InstalledVersions;

abstract class Adapter
{
    /** Машинный идентификатор (используется в URL и в JSON). */
    abstract public static function id(): string;

    /** Отображаемое имя. */
    abstract public static function label(): string;

    /** Пакет composer. */
    abstract public static function package(): string;

    abstract public static function homepage(): string;

    /** Умеет ли библиотека отдавать строки потоком, не разбирая файл целиком. */
    abstract public static function streaming(): bool;

    /** Короткая характеристика подхода — выводится в таблице. */
    abstract public static function note(): string;

    /**
     * Прочитать все строки первого листа.
     * Реализация должна использовать самый экономичный штатный способ библиотеки.
     */
    abstract public function readAll(string $file): Tally;

    /** Открыть файл и получить первую строку. */
    abstract public function firstRow(string $file): Tally;

    /**
     * Получить весь лист одним массивом. Возврат null означает «сценарий не поддержан».
     */
    public function toArray(string $file): ?Tally
    {
        return null;
    }

    public function run(Mode $mode, string $file): ?Tally
    {
        return match ($mode) {
            Mode::ReadAll  => $this->readAll($file),
            Mode::FirstRow => $this->firstRow($file),
            Mode::ToArray  => $this->toArray($file),
        };
    }

    public static function version(): string
    {
        try {
            return InstalledVersions::getPrettyVersion(static::package()) ?? 'dev';
        } catch (\Throwable) {
            return 'неизвестно';
        }
    }

    /**
     * Подсчёт «полезной работы» по массиву значений строки.
     *
     * @param iterable<mixed> $values
     *
     * @return array{0:int, 1:int} [непустых ячеек, суммарная длина значений]
     */
    final protected static function measureRow(iterable $values): array
    {
        $cells = 0;
        $bytes = 0;
        foreach ($values as $value) {
            if ($value === null || $value === '') {
                continue;
            }
            ++$cells;
            $bytes += match (true) {
                is_string($value)            => \strlen($value),
                is_int($value), is_float($value) => \strlen((string) $value),
                is_bool($value)              => 1,
                $value instanceof \DateTimeInterface => 19,
                default                      => \strlen((string) json_encode($value)),
            };
        }

        return [$cells, $bytes];
    }
}
