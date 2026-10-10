<?php

namespace Tests\Feature;

use App\Models\BuildingLevel;
use App\Models\BuildingType;
use App\Models\TroopType;
use Tests\GameTestCase;

class CatalogTest extends GameTestCase
{
    public function test_katalog_ucu_butun_tanimlari_dondurur(): void
    {
        $this->getJson('/api/v1/katalog')
            ->assertOk()
            ->assertJsonStructure([
                'version', 'grid', 'resources', 'battle', 'leagues',
                'buildings' => [['key', 'name', 'category', 'w', 'h', 'levels']],
                'troops'    => [['key', 'name', 'housing', 'levels']],
                'heroes'    => [['key', 'name', 'title', 'levels']],
                'spells'    => [['key', 'name', 'levels']],
                'biomes', 'world' => ['seed', 'width', 'height'],
            ]);
    }

    public function test_butun_bina_tiplerinin_tam_seviye_tablosu_var(): void
    {
        foreach (BuildingType::with('levels')->get() as $type) {
            $this->assertCount(
                $type->max_level,
                $type->levels,
                "{$type->key}: {$type->max_level} seviye beklenirken ".count($type->levels)." bulundu"
            );

            // Seviyeler 1..max arası kesintisiz olmalı
            $levels = $type->levels->pluck('level')->sort()->values()->all();
            $this->assertSame(range(1, $type->max_level), $levels, "{$type->key}: seviyeler kesintisiz olmalı");
        }
    }

    public function test_butun_asker_tiplerinin_tam_seviye_tablosu_var(): void
    {
        foreach (TroopType::with('levels')->get() as $type) {
            $this->assertCount($type->max_level, $type->levels, "{$type->key} seviye sayısı");
            $this->assertSame(
                range(1, $type->max_level),
                $type->levels->pluck('level')->sort()->values()->all(),
                "{$type->key}: seviyeler kesintisiz olmalı"
            );
        }
    }

    public function test_bina_istatistikleri_seviyeyle_artar(): void
    {
        foreach (BuildingType::with('levels')->get() as $type) {
            $previous = null;
            foreach ($type->levels as $level) {
                if ($previous) {
                    $this->assertGreaterThanOrEqual(
                        $previous->hitpoints,
                        $level->hitpoints,
                        "{$type->key} sv{$level->level}: can azalmamalı"
                    );
                    if ($type->produces) {
                        $this->assertGreaterThanOrEqual(
                            $previous->production_per_hour,
                            $level->production_per_hour,
                            "{$type->key} sv{$level->level}: üretim azalmamalı"
                        );
                    }
                    if ($previous->damage_per_second > 0) {
                        $this->assertGreaterThanOrEqual(
                            $previous->damage_per_second,
                            $level->damage_per_second,
                            "{$type->key} sv{$level->level}: hasar azalmamalı"
                        );
                    }
                }
                $previous = $level;
            }
        }
    }

    public function test_otag_seviye_gereksinimleri_artan_sirada(): void
    {
        foreach (BuildingType::with('levels')->get() as $type) {
            $previous = 0;
            foreach ($type->levels as $level) {
                $this->assertGreaterThanOrEqual(
                    $previous,
                    $level->requires_otag_level,
                    "{$type->key} sv{$level->level}: Otağ gereksinimi geriye gitmemeli"
                );
                $previous = $level->requires_otag_level;
            }
        }
    }

    public function test_savunma_yapilarinin_menzili_ve_hasari_var(): void
    {
        $defenses = BuildingType::with('levels')->where('category', 'savunma')->get();
        $this->assertGreaterThan(5, $defenses->count());

        foreach ($defenses as $type) {
            // Kurt İni hasar vermez; asker doğurur.
            if ($type->key === 'kurt_ini') {
                continue;
            }

            $level = $type->levels->first();
            $this->assertGreaterThan(0, $level->damage_per_second, "{$type->key} hasar vermeli");
            $this->assertGreaterThan(0, (float) $level->attack_range, "{$type->key} menzili olmalı");
        }
    }

    public function test_tuzaklarin_hasari_veya_asker_dogurmasi_var(): void
    {
        foreach (BuildingType::with('levels')->where('category', 'tuzak')->get() as $type) {
            $extra = $type->levels->first()->extra ?? [];
            $this->assertTrue(
                ($extra['trap_damage'] ?? 0) > 0 || isset($extra['spawn']),
                "{$type->key}: ya hasar vermeli ya asker doğurmalı"
            );
        }
    }

    public function test_kaynak_binalari_uretim_yapar_depolar_kapasite_verir(): void
    {
        foreach (BuildingType::with('levels')->get() as $type) {
            $first = $type->levels->first();

            if ($type->produces) {
                $this->assertGreaterThan(0, $first->production_per_hour, "{$type->key} üretmeli");
            }
            if ($type->stores) {
                $this->assertGreaterThan(0, $first->storage_capacity, "{$type->key} depolamalı");
            }
        }
    }

    public function test_asker_alan_maliyeti_ve_egitim_suresi_tutarli(): void
    {
        foreach (TroopType::all() as $type) {
            $this->assertGreaterThan(0, $type->housing, "{$type->key} alan kaplamalı");
            $this->assertGreaterThan(0, $type->train_seconds, "{$type->key} eğitim süresi olmalı");
            $this->assertGreaterThan(
                0,
                array_sum($type->trainCosts()),
                "{$type->key} bir bedeli olmalı"
            );
        }
    }

    public function test_duvar_kiricinin_duvar_carpani_yuksek(): void
    {
        $gurzcu = TroopType::where('key', 'gurzcu')->firstOrFail();

        $this->assertSame('duvar', $gurzcu->preferred_target);
        $this->assertGreaterThanOrEqual(20, (float) $gurzcu->target_damage_multiplier);
    }

    public function test_otag_her_seviyede_depolama_saglar(): void
    {
        $otag = BuildingType::with('levels')->where('key', 'otag')->firstOrFail();

        foreach ($otag->levels as $level) {
            $this->assertGreaterThan(0, $level->storage_capacity, "Otağ sv{$level->level} depolamalı");
        }
    }

    public function test_bina_maliyetleri_seviyeyle_artar(): void
    {
        foreach (BuildingType::with('levels')->get() as $type) {
            $previous = 0;
            foreach ($type->levels as $level) {
                if ($level->level === 1) {
                    continue;
                }
                $total = array_sum($level->costs());
                $this->assertGreaterThan(
                    $previous,
                    $total,
                    "{$type->key} sv{$level->level}: maliyet artmalı"
                );
                $previous = $total;
            }
        }
    }
}
