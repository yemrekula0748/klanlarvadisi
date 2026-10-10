<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Models\User;
use App\Services\VillageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly VillageService $villages) {}

    /** Yeni oyuncu: kullanıcı + oyuncu profili + oba bir arada kurulur. */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'        => ['required', 'email', 'max:190', 'unique:users,email'],
            'password'     => ['required', 'string', 'min:8', 'max:72'],
            'player_name'  => ['required', 'string', 'min:3', 'max:24',
                'regex:/^[\p{L}\p{N} _\-]+$/u', 'unique:players,name'],
            'village_name' => ['nullable', 'string', 'max:40'],
            'device'       => ['nullable', 'string', 'max:64'],
        ], [], [
            'player_name' => 'oyuncu adı',
            'email'       => 'e-posta',
            'password'    => 'şifre',
        ]);

        $user = User::create([
            'name'     => $data['player_name'],
            'email'    => $data['email'],
            'password' => $data['password'],
        ]);

        $player = $this->villages->createForUser(
            $user,
            $data['player_name'],
            $data['village_name'] ?? null
        );

        return response()->json([
            'token'  => $user->createToken($data['device'] ?? 'unity')->plainTextToken,
            'player' => $this->playerPayload($player),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
            'device'   => ['nullable', 'string', 'max:64'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['E-posta veya şifre hatalı.'],
            ]);
        }

        $player = $user->player;
        $player?->forceFill(['last_seen_at' => now()])->saveQuietly();

        return response()->json([
            'token'  => $user->createToken($data['device'] ?? 'unity')->plainTextToken,
            'player' => $player ? $this->playerPayload($player) : null,
        ]);
    }

    /** Konuk giriş: Unity'de hesap açmadan denemek için. */
    public function guest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device' => ['required', 'string', 'min:8', 'max:64'],
        ]);

        $email = 'konuk-'.sha1($data['device']).'@konuk.local';
        $user = User::where('email', $email)->first();

        if ($user) {
            return response()->json([
                'token'  => $user->createToken('konuk')->plainTextToken,
                'player' => $user->player ? $this->playerPayload($user->player) : null,
            ]);
        }

        $name = 'Konuk'.random_int(100000, 999999);
        while (Player::where('name', $name)->exists()) {
            $name = 'Konuk'.random_int(100000, 999999);
        }

        $user = User::create([
            'name'     => $name,
            'email'    => $email,
            'password' => bin2hex(random_bytes(16)),
        ]);

        $player = $this->villages->createForUser($user, $name);

        return response()->json([
            'token'  => $user->createToken('konuk')->plainTextToken,
            'player' => $this->playerPayload($player),
            'guest'  => true,
        ], 201);
    }

    public function me(Request $request): JsonResponse
    {
        $player = $request->user()->player;

        return response()->json([
            'player' => $player ? $this->playerPayload($player) : null,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Çıkış yapıldı.']);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $player = $request->attributes->get('player');

        $data = $request->validate([
            'avatar'       => ['nullable', 'string', Rule::in([
                'kurt', 'kagan', 'kam', 'okcu', 'alp', 'hatun', 'konrul', 'tepegoz',
            ])],
            'village_name' => ['nullable', 'string', 'max:40'],
        ]);

        if (isset($data['avatar'])) {
            $player->update(['avatar' => $data['avatar']]);
        }
        if (isset($data['village_name'])) {
            $player->village?->update(['name' => $data['village_name']]);
        }

        return response()->json(['player' => $this->playerPayload($player->refresh())]);
    }

    private function playerPayload(Player $player): array
    {
        return [
            'id'       => $player->id,
            'name'     => $player->name,
            'avatar'   => $player->avatar,
            'level'    => $player->level,
            'trophies' => $player->trophies,
            'league'   => $player->league(),
            'gok_tasi' => (int) $player->gok_tasi,
            'village'  => $player->village ? [
                'id'         => $player->village->id,
                'name'       => $player->village->name,
                'otag_level' => $player->village->otag_level,
                'world'      => ['x' => $player->village->world_x, 'y' => $player->village->world_y],
            ] : null,
            'clan' => $player->clan ? [
                'id'   => $player->clan->id,
                'name' => $player->clan->name,
                'tag'  => $player->clan->tag,
                'role' => $player->clan_role,
            ] : null,
        ];
    }
}
