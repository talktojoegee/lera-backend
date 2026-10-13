<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable/* , HasApiTokens */;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
        'type',
        'id_no',
        'mobile_no',
        'sector',
        'role',
        'lga',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getJWTIdentifier()
    {
        // TODO: Implement getJWTIdentifier() method.
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        // TODO: Implement getJWTCustomClaims() method.
        return [];
    }

    public function getAuthIdentifierName()
    {
        return 'username';
    }

    public function getLga()
    {
        return $this->belongsTo(Lga::class, 'lga_id');
    }

    /**
     * Resolve allowed LGA IDs from the user's sector column.
     *
     * Sector may contain comma-separated LGA names (e.g. "Alphacen,Newwaves")
     * or numeric IDs. Returns unique integer IDs.
     *
     * @return list<int>
     */
    public function sectorLgaIds(): array
    {
        $raw = trim((string) ($this->sector ?? ''));
        if ($raw === '') {
            return [];
        }

        $tokens = array_values(array_filter(array_map('trim', explode(',', $raw))));
        if ($tokens === []) {
            return [];
        }

        $ids = [];
        $names = [];

        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }
            if (ctype_digit($token)) {
                $ids[] = (int) $token;
            } else {
                $names[] = $token;
            }
        }

        if ($names !== []) {
            // Case-insensitive match by lga_name.
            $nameIds = Lga::query()
                ->where(function ($q) use ($names) {
                    foreach ($names as $name) {
                        $q->orWhereRaw('LOWER(lga_name) = ?', [strtolower($name)]);
                    }
                })
                ->pluck('id')
                ->all();

            $ids = array_merge($ids, array_map('intval', $nameIds));
        }

        $ids = array_values(array_unique(array_filter($ids, fn ($id) => $id > 0)));
        sort($ids);

        return $ids;
    }

    public static function fetchAllAdminUsers($type, $limit, $skip)
    {
        return User::where('type', $type)
            ->skip($skip)
            ->take($limit)
            ->orderBy('id', 'DESC')
            ->get();
    }
}
