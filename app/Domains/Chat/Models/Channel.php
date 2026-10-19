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

    protected $fillable = ['name', 'slug', 'description', 'type', 'direct_key', 'created_by'];

    protected $hidden = ['direct_key'];

    protected static function booted(): void
    {
        static::creating(function (Channel $channel) {
            if (empty($channel->slug)) {
                $channel->slug = static::uniqueSlug($channel->name ?? 'direct message');
            }
        });
    }

    // Different names can share a slug ("Room" / "Room!") and symbol-only names slug to "",
    // so fall back to "channel" and suffix -2, -3, ... until the slug is free.
    private static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'channel';
        $slug = $base;

        for ($i = 2; static::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('joined_at', 'last_read_message_id', 'cleared_message_id', 'hidden_at');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function isPublic(): bool
    {
        return $this->type === 'public';
    }

    public function isDirect(): bool
    {
        return $this->type === 'direct';
    }

    public static function directKeyFor(User $a, User $b): string
    {
        return min($a->id, $b->id).':'.max($a->id, $b->id);
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }
}
