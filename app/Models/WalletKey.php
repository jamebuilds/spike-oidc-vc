<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletKey extends Model
{
    /** @use HasFactory<\Database\Factories\WalletKeyFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'algorithm',
        'public_jwk',
        'private_jwk',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'public_jwk' => 'array',
            'private_jwk' => 'encrypted',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
