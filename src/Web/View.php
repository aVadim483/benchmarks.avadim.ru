<?php

declare(strict_types=1);

namespace App\Web;

final class View
{
    private const DIR = __DIR__ . '/../../templates';

    /** @param array<string, mixed> $data */
    public static function render(string $template, array $data = [], int $status = 200): string
    {
        http_response_code($status);

        $content = self::partial($template, $data);

        return self::partial('layout', $data + [
            'content' => $content,
            'title'   => $data['title'] ?? 'Бенчмарк PHP-библиотек чтения XLSX',
        ]);
    }

    /** @param array<string, mixed> $data */
    public static function partial(string $template, array $data = []): string
    {
        $__file = self::DIR . '/' . $template . '.php';
        if (!is_file($__file)) {
            throw new \RuntimeException("Шаблон не найден: {$template}");
        }

        // Имена локальных переменных начинаются с __, чтобы данные шаблона
        // (например $file) не пересекались с внутренней кухней рендера.
        extract($data, EXTR_OVERWRITE);
        unset($data, $template);

        ob_start();
        try {
            require $__file;
        } catch (\Throwable $e) {
            ob_end_clean();

            throw $e;
        }

        return (string) ob_get_clean();
    }

    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
