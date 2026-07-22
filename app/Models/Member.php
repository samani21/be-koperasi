<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // Sesuaikan dengan migration
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Member extends Model
{
    use SoftDeletes;

    /**
     * Daftar kolom yang diizinkan untuk diisi.
     */
    protected $fillable = [
        'user_id',
        'photo',
        'member_number',
        'full_name',
        'address',
        'phone',
    ];

    /**
     * Relasi balik ke tabel users (Satu member dimiliki oleh satu user)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
