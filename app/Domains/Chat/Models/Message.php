<?php

declare(strict_types=1);

namespace App\Domains\Chat\Models;

use App\Models\User;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected static function newFactory(): Factory
    {
        return MessageFactory::new();
    }

    protected $fillable = ['sender_id', 'channel_id', 'content', 'read_at'];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
