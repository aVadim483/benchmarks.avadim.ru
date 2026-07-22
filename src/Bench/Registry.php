<?php

declare(strict_types=1);

namespace App\Bench;

use App\Bench\Adapter\FastExcelReaderAdapter;
use App\Bench\Adapter\OpenSpoutAdapter;
use App\Bench\Adapter\PhpSpreadsheetAdapter;
use App\Bench\Adapter\SimpleXlsxAdapter;

final class Registry
{
    /** @var list<class-string<Adapter>> */
    private const ADAPTERS = [
        FastExcelReaderAdapter::class,
        OpenSpoutAdapter::class,
        PhpSpreadsheetAdapter::class,
        SimpleXlsxAdapter::class,
    ];

    /** Библиотека, ради которой всё затевалось — подсвечивается в интерфейсе. */
    public const SUBJECT = 'fast-excel-reader';

    /** @return list<class-string<Adapter>> */
    public static function classes(): array
    {
        return self::ADAPTERS;
    }

    /** @return list<string> */
    public static function ids(): array
    {
        return array_map(static fn (string $class): string => $class::id(), self::ADAPTERS);
    }

    /** @return class-string<Adapter> */
    public static function classFor(string $id): string
    {
        foreach (self::ADAPTERS as $class) {
            if ($class::id() === $id) {
                return $class;
            }
        }

        throw new \InvalidArgumentException("Неизвестный адаптер: {$id}");
    }

    public static function make(string $id): Adapter
    {
        $class = self::classFor($id);

        return new $class();
    }

    /**
     * Описание библиотек для интерфейса.
     *
     * @return list<array{id:string, label:string, package:string, version:string, homepage:string, streaming:bool, note:string, subject:bool}>
     */
    public static function describe(): array
    {
        $out = [];
        foreach (self::ADAPTERS as $class) {
            $out[] = [
                'id'        => $class::id(),
                'label'     => $class::label(),
                'package'   => $class::package(),
                'version'   => $class::version(),
                'homepage'  => $class::homepage(),
                'streaming' => $class::streaming(),
                'note'      => $class::note(),
                'subject'   => $class::id() === self::SUBJECT,
            ];
        }

        return $out;
    }
}
