<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
            'is_active' => 'boolean',

        ];
    }
    public function roles(): BelongsToMany
{
    return $this->belongsToMany(Role::class);
}

public function hasRole(string $slug): bool
{
    return $this->roles()
        ->where('slug', $slug)
        ->exists();
}

public function hasPermission(string $slug): bool
{
    if ($this->hasRole('admin')) {
        return true;
    }

    return $this->roles()
        ->whereHas('permissions', function ($query) use ($slug) {
            $query->where('slug', $slug);
        })
        ->exists();
}
}
