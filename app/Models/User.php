<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'telephone',
        'role',
        'negeri_id',
        'bandar_id',
        'kadun_id',
        'status',
        'approved_by',
        'approved_at',
        'password',
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
            'approved_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the negeri (state) that the user belongs to.
     */
    public function negeri()
    {
        return $this->belongsTo(Negeri::class, 'negeri_id');
    }

    /**
     * Get the bandar (city/parliament) that the user belongs to.
     */
    public function bandar()
    {
        return $this->belongsTo(Bandar::class, 'bandar_id');
    }

    /**
     * Get the kadun that the user belongs to.
     */
    public function kadun()
    {
        return $this->belongsTo(Kadun::class, 'kadun_id');
    }

    /**
     * Get the user who approved this user.
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the users that this user has approved.
     */
    public function approvedUsers()
    {
        return $this->hasMany(User::class, 'approved_by');
    }

    /**
     * Get the data pengundi submitted by this user.
     */
    public function dataPengundi()
    {
        return $this->hasMany(DataPengundi::class, 'submitted_by');
    }

    /**
     * Get the hasil culaan submitted by this user.
     */
    public function hasilCulaan()
    {
        return $this->hasMany(HasilCulaan::class, 'submitted_by');
    }

    /**
     * Scope a query to only include pending users.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include approved users.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope a query to filter users by territory.
     */
    public function scopeForTerritory($query, $negeriId = null, $bandarId = null, $kadunId = null)
    {
        if ($negeriId) {
            $query->where('negeri_id', $negeriId);
        }
        if ($bandarId) {
            $query->where('bandar_id', $bandarId);
        }
        if ($kadunId) {
            $query->where('kadun_id', $kadunId);
        }
        return $query;
    }

    /**
     * Check if user is Super Admin.
     */
    public function isSuperAdmin()
    {
        return $this->role === 'super_admin';
    }

    /**
     * Check if user is Admin.
     */
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is User.
     */
    public function isUser()
    {
        return $this->role === 'user';
    }

    /**
     * Check if user is approved.
     */
    public function isApproved()
    {
        return $this->status === 'approved';
    }
}
