<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Credential extends Model
{
    /** @use HasFactory<\Database\Factories\CredentialFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'issuer',
        'type',
        'raw_sd_jwt',
        'payload_claims',
        'disclosure_mapping',
        'cnf_jwk',
        'issued_at',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'raw_sd_jwt' => 'encrypted',
            'payload_claims' => 'array',
            'disclosure_mapping' => 'array',
            'cnf_jwk' => 'array',
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
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
