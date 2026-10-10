<?php

namespace App\Services\Combat;

/**
 * 44x44 oba ızgarasında yol bulma.
 *
 * Binalar geçilemez; duvar hücreleri yüksek maliyetle "geçilebilir" sayılır.
 * Böylece asker açık kapıdan dolaşmayı tercih eder, ama dolambaç çok uzunsa
 * duvarı kırmayı seçer — Clash of Clans'teki davranışın aynısı.
 *
 * Başarım: her asker için ayrı A* çalıştırmak yerine, HEDEF BAŞINA bir
 * "uzaklık alanı" (Dijkstra) hesaplanır ve aynı hedefe giden bütün askerler
 * bu alanı paylaşır. Alan, ızgara değiştiğinde (bina yıkıldığında) geçersiz
 * kılınır. 230 askerli bir savaşta binlerce A* çağrısı yerine onlarca
 * Dijkstra taraması yapılır.
 */
final class PathFinder
{
    /** @var array<int,int> hücre => 0 boş, 1 bina, 2 duvar */
    private array $cells;

    /** @var array<string,array<int,float>> hedef imzası => uzaklık alanı */
    private array $fields = [];

    private int $generation = 0;

    public function __construct(private readonly int $size, array $cells)
    {
        $this->cells = $cells;
    }

    private function idx(int $x, int $y): int
    {
        return $y * $this->size + $x;
    }

    public function blocked(int $x, int $y): bool
    {
        return ($this->cells[$this->idx($x, $y)] ?? 0) === 1;
    }

    public function isWall(int $x, int $y): bool
    {
        return ($this->cells[$this->idx($x, $y)] ?? 0) === 2;
    }

    /** Hücre durumunu değiştirir ve bütün önbelleği geçersiz kılar. */
    public function setCell(int $x, int $y, int $v): void
    {
        $i = $this->idx($x, $y);
        if (($this->cells[$i] ?? 0) === $v) {
            return;
        }

        $this->cells[$i] = $v;
        $this->fields = [];
        $this->generation++;
    }

    public function generation(): int
    {
        return $this->generation;
    }

    /**
     * Hedef hücre kümesine giden yolu döndürür (başlangıç hariç).
     *
     * @param  array<int,array{0:int,1:int}>  $goals
     * @return array<int,array{0:int,1:int}>
     */
    public function find(int $sx, int $sy, array $goals, string $cacheKey = ''): array
    {
        $field = $this->field($goals, $cacheKey);
        if (! $field) {
            return [];
        }

        return $this->descend($sx, $sy, $field, $goals);
    }

    /**
     * Hedefe olan uzaklık alanını (Dijkstra) üretir; aynı hedef için
     * yeniden kullanılır.
     *
     * @param  array<int,array{0:int,1:int}>  $goals
     * @return array<int,float>
     */
    public function field(array $goals, string $cacheKey = ''): array
    {
        if (! $goals) {
            return [];
        }

        $key = $cacheKey !== '' ? $cacheKey : implode(';', array_map(fn ($g) => $g[0].','.$g[1], $goals));
        if (isset($this->fields[$key])) {
            return $this->fields[$key];
        }

        $size = $this->size;
        $wallCost = (float) config('game.battle.wall_path_cost');

        $goalSet = [];
        $dist = [];
        $queue = new \SplPriorityQueue;
        $queue->setExtractFlags(\SplPriorityQueue::EXTR_DATA);

        foreach ($goals as [$gx, $gy]) {
            if ($gx < 0 || $gy < 0 || $gx >= $size || $gy >= $size) {
                continue;
            }
            $i = $this->idx($gx, $gy);
            $goalSet[$i] = true;
            $dist[$i] = 0.0;
            $queue->insert($i, 0);
        }

        if (! $dist) {
            return [];
        }

        $closed = [];

        while (! $queue->isEmpty()) {
            $current = $queue->extract();
            if (isset($closed[$current])) {
                continue;
            }
            $closed[$current] = true;

            $cx = $current % $size;
            $cy = intdiv($current, $size);
            $base = $dist[$current];

            for ($dy = -1; $dy <= 1; $dy++) {
                for ($dx = -1; $dx <= 1; $dx++) {
                    if ($dx === 0 && $dy === 0) {
                        continue;
                    }

                    $nx = $cx + $dx;
                    $ny = $cy + $dy;
                    if ($nx < 0 || $ny < 0 || $nx >= $size || $ny >= $size) {
                        continue;
                    }

                    $n = $this->idx($nx, $ny);
                    $cell = $this->cells[$n] ?? 0;

                    // Bina hücresinden geçilmez (hedefin kendisi hariç).
                    if ($cell === 1 && ! isset($goalSet[$n])) {
                        continue;
                    }

                    // Çapraz adımda köşe kesilemez.
                    if ($dx !== 0 && $dy !== 0) {
                        $a = $this->cells[$this->idx($cx + $dx, $cy)] ?? 0;
                        $b = $this->cells[$this->idx($cx, $cy + $dy)] ?? 0;
                        if ($a === 1 && $b === 1) {
                            continue;
                        }
                    }

                    $step = ($dx !== 0 && $dy !== 0) ? 1.4142 : 1.0;
                    if ($cell === 2 && ! isset($goalSet[$n])) {
                        $step += $wallCost;
                    }

                    $candidate = $base + $step;
                    if (isset($dist[$n]) && $candidate >= $dist[$n]) {
                        continue;
                    }

                    $dist[$n] = $candidate;
                    $queue->insert($n, -$candidate);
                }
            }
        }

        // Önbellek sınırı: çok sayıda hedef varsa en eskiyi düşür.
        if (count($this->fields) > 64) {
            array_shift($this->fields);
        }

        return $this->fields[$key] = $dist;
    }

    /**
     * Uzaklık alanında yokuş aşağı inerek yolu çıkarır.
     *
     * @param  array<int,float>  $field
     * @return array<int,array{0:int,1:int}>
     */
    private function descend(int $sx, int $sy, array $field, array $goals): array
    {
        $size = $this->size;
        $current = $this->idx($sx, $sy);

        if (! isset($field[$current])) {
            // Asker kapalı bir cepte: en yakın erişilebilir komşuyu bul.
            $current = $this->nearestReachable($sx, $sy, $field);
            if ($current === null) {
                return [];
            }
        }

        $path = [];
        $guard = 0;

        while (($field[$current] ?? 0.0) > 0.0 && $guard++ < 2048) {
            $cx = $current % $size;
            $cy = intdiv($current, $size);

            $best = null;
            $bestCost = $field[$current];

            for ($dy = -1; $dy <= 1; $dy++) {
                for ($dx = -1; $dx <= 1; $dx++) {
                    if ($dx === 0 && $dy === 0) {
                        continue;
                    }
                    $nx = $cx + $dx;
                    $ny = $cy + $dy;
                    if ($nx < 0 || $ny < 0 || $nx >= $size || $ny >= $size) {
                        continue;
                    }

                    $n = $this->idx($nx, $ny);
                    if (! isset($field[$n]) || $field[$n] >= $bestCost) {
                        continue;
                    }

                    $bestCost = $field[$n];
                    $best = $n;
                }
            }

            if ($best === null) {
                break;
            }

            $current = $best;
            $path[] = [$current % $size, intdiv($current, $size)];
        }

        return $path;
    }

    /** @param  array<int,float>  $field */
    private function nearestReachable(int $sx, int $sy, array $field): ?int
    {
        for ($r = 1; $r <= 6; $r++) {
            for ($dy = -$r; $dy <= $r; $dy++) {
                for ($dx = -$r; $dx <= $r; $dx++) {
                    if (max(abs($dx), abs($dy)) !== $r) {
                        continue;
                    }
                    $nx = $sx + $dx;
                    $ny = $sy + $dy;
                    if ($nx < 0 || $ny < 0 || $nx >= $this->size || $ny >= $this->size) {
                        continue;
                    }
                    $n = $this->idx($nx, $ny);
                    if (isset($field[$n])) {
                        return $n;
                    }
                }
            }
        }

        return null;
    }
}
