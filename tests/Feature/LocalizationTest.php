<?php

namespace Tests\Feature;

use Tests\GameTestCase;

/**
 * Oyun Türkçe oynanır. Unity istemcisi sunucunun `message` alanını doğrudan
 * oyuncuya gösterdiği için hata iletilerinin çeviri anahtarı (ör.
 * "validation.required") değil, okunur Türkçe olması gerekir.
 */
class LocalizationTest extends GameTestCase
{
    public function test_varsayilan_dil_turkce(): void
    {
        $this->assertSame('tr', config('app.locale'));
        $this->assertSame('tr', config('app.fallback_locale'));
    }

    public function test_dogrulama_iletileri_turkce(): void
    {
        $yanit = $this->postJson('/api/v1/konuk', []);

        $yanit->assertStatus(422)
            ->assertJsonPath('code', 'dogrulama')
            ->assertJsonPath('errors.device.0', 'cihaz alanı zorunludur.');

        $this->assertStringNotContainsString('validation.', $yanit->json('message'));
    }

    public function test_cok_hatali_istekte_ozet_turkce(): void
    {
        $yanit = $this->postJson('/api/v1/kayit', [
            'email'    => 'bozuk',
            'password' => '123',
        ]);

        $yanit->assertStatus(422);

        // Laravel'in çeviriye kapalı "(and N more errors)" metni sızmamalı.
        $this->assertStringNotContainsString('more error', $yanit->json('message'));
        $this->assertStringContainsString('hata daha', $yanit->json('message'));
    }

    public function test_hatali_girise_turkce_yanit(): void
    {
        $yanit = $this->postJson('/api/v1/giris', [
            'email'    => 'olmayan@ornek.com',
            'password' => 'yanlissifre',
        ]);

        $yanit->assertStatus(422);
        $this->assertStringNotContainsString('auth.', $yanit->json('message'));
    }

    public function test_ceviri_dosyalari_eksiksiz(): void
    {
        foreach (['validation', 'auth', 'passwords', 'pagination'] as $dosya) {
            $this->assertFileExists(base_path("lang/tr/{$dosya}.php"));
        }

        // Denetleyicilerde kullanılan her kural için ileti bulunmalı: çeviri
        // eksikse __() anahtarın kendisini döndürür.
        foreach (['required', 'string', 'integer', 'numeric', 'email', 'array',
                  'boolean', 'unique', 'exists', 'in', 'regex', 'min', 'max', 'size'] as $kural) {
            $this->assertNotSame(
                "validation.{$kural}",
                __("validation.{$kural}"),
                "validation.{$kural} çevirisi eksik"
            );
        }
    }
}
