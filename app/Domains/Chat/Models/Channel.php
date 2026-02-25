<?php

declare(strict_types=1);

namespace App\Domains\Chat\Models;

use App\Models\User;
use Database\Factories\ChannelFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Channel extends Model
{
    use HasFactory;

    protected static function newFactory(): Factory
    {
        return ChannelFactory::new();
    }

    protected $fillable = ['name', 'slug', 'description', 'type', 'created_by'];

    protected static function booted(): void
    {
        static::creating(function (Channel $channel) {
            if (empty($channel->slug)) {
                $channel->slug = Str::slug($channel->name);
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('joined_at');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function isPublic(): bool
    {
        return $this->type === 'public';
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }
}
