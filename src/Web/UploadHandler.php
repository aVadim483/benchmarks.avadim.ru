<?php

declare(strict_types=1);

namespace App\Web;

use App\Bench\FileProbe;
use App\Support\Config;
use App\Support\Format;

final class UploadHandler
{
    /**
     * @param array{name?:string, type?:string, tmp_name?:string, error?:int, size?:int} $file
     *
     * @return array{path:string, name:string, probe:array<string, mixed>}
     *
     * @throws UploadException
     */
    public function accept(array $file, string $jobId): array
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_OK) {
            throw new UploadException(self::errorMessage($error));
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new UploadException('Файл не был загружен');
        }

        $maxBytes = (int) Config::get('web.max_upload_bytes', 25 * 1024 * 1024);
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0) {
            throw new UploadException('Файл пустой');
        }
        if ($size > $maxBytes) {
            throw new UploadException('Файл больше допустимых ' . Format::bytes($maxBytes));
        }

        $originalName = (string) ($file['name'] ?? 'upload.xlsx');
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));

        /** @var list<string> $allowed */
        $allowed = Config::get('web.allowed_ext', ['xlsx']);
        if (!\in_array($extension, $allowed, true)) {
            throw new UploadException('Поддерживаются только файлы ' . implode(', ', array_map(
                static fn (string $e): string => '.' . $e,
                $allowed,
            )));
        }

        $target = Config::path('uploads') . '/' . $jobId . '.' . $extension;
        if (!move_uploaded_file($tmpName, $target)) {
            throw new UploadException('Не удалось сохранить файл на сервере');
        }

        $probe = FileProbe::inspect($target, self::safeName($originalName));
        if ($probe['valid'] !== true) {
            @unlink($target);
            throw new UploadException((string) ($probe['error'] ?? 'Файл не похож на книгу XLSX'));
        }

        return ['path' => $target, 'name' => self::safeName($originalName), 'probe' => $probe];
    }

    public static function safeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? 'file.xlsx';

        return mb_substr(trim($name), 0, 120) ?: 'file.xlsx';
    }

    private static function errorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Файл превышает лимит загрузки на сервере '
                . '(upload_max_filesize / post_max_size)',
            UPLOAD_ERR_PARTIAL    => 'Файл загружен не полностью, попробуйте ещё раз',
            UPLOAD_ERR_NO_FILE    => 'Выберите файл для загрузки',
            UPLOAD_ERR_NO_TMP_DIR => 'На сервере не настроена временная папка для загрузок',
            UPLOAD_ERR_CANT_WRITE => 'Сервер не смог записать файл на диск',
            UPLOAD_ERR_EXTENSION  => 'Загрузка заблокирована расширением PHP',
            default               => 'Не удалось загрузить файл (код ' . $code . ')',
        };
    }
}
