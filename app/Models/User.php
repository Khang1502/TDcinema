<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\ResetPasswordNotification as TDResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Support\AdminAccess;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',                 // admin, manager, staff, customer
        'admin_permissions',    // null = quyền mặc định theo vai trò
        'membership_level_id',  // FK
        'cinema_id',            // FK (staff/manager)
        'status',               // active / blocked
        'is_active',            // legacy flag, giữ để tương thích
        'phone',
        'birthday',
        'address',
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
            'birthday' => 'date',
            'admin_permissions' => 'array',
            'points' => 'integer',
            'total_spent' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::forceDeleted(fn (self $user) => CinemaCornerLike::where('user_id', $user->id)->delete());
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new TDResetPasswordNotification($token));
    }
    public function membershipLevel()
    {
        return $this->belongsTo(MembershipLevel::class);
    }

    public function getMemberTierAttribute(): ?MembershipLevel
    {
        return app(\App\Services\MembershipService::class)->levelFor((int) $this->points);
    }

    public function cinema()
    {
        return $this->belongsTo(Cinema::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function foodOrders()
    {
        return $this->hasMany(FoodOrder::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function hasAdminPermission(string $permission): bool
    {
        return AdminAccess::allows($this, $permission);
    }

    public function adminRoleLabel(): string
    {
        return AdminAccess::roleLabel($this->role);
    }
}
