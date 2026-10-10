<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Oyun kuralı ihlalleri. Unity istemcisine
 * {"message": "...", "code": "kaynak_yetersiz"} biçiminde döner;
 * istemci `code` alanına göre yerelleştirilmiş uyarı gösterir.
 */
class GameException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'oyun_hatasi',
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }

    public static function kaynakYetersiz(string $resource): self
    {
        $label = config("game.resource_labels.$resource", $resource);

        return new self("Yeterli $label yok.", 'kaynak_yetersiz');
    }

    public static function izinYok(string $message = 'Bu işlem için yetkin yok.'): self
    {
        return new self($message, 'izin_yok', 403);
    }

    public static function bulunamadi(string $message = 'Kayıt bulunamadı.'): self
    {
        return new self($message, 'bulunamadi', 404);
    }
}
