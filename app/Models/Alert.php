<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A broadcast from Alerts & Broadcast. It shows in Alerts in the
 * residents' app, the responders' app, or both (type = Citizens,
 * Responders or Both).
 *
 * Clearing past alerts (panel comment #22): an alert shows in the apps
 * until expires_at — 24 hours after it was sent — or until an admin
 * clears it early (cleared_at, cleared_by). Either way it stays in
 * Broadcast History. Residents and responders can also clear alerts on
 * their own phone; that only hides them on that phone.
 *
 * Item 50: the admin no longer picks when an alert clears (it was 1 to 30
 * days, "Clears from the apps after" on the broadcast form). Every alert
 * clears by itself 24 hours after it's sent.
 */
class Alert extends Model
{
    /** Every alert clears from the apps by itself this long after it's sent. */
    public const CLEARS_AFTER_HOURS = 24;

    protected $fillable = [
        'title', 'subtitle', 'body', 'type', 'user_id',
        'expires_at', 'cleared_at', 'cleared_by',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'cleared_at' => 'datetime',
    ];

    /** The admin who sent it. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The admin who cleared it early. */
    public function clearedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cleared_by');
    }

    /** Still in the apps: not cleared by an admin and not past its time. */
    public function scopeShowing(Builder $query): Builder
    {
        return $query->whereNull('cleared_at')
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function isShowing(): bool
    {
        return $this->cleared_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /** Cleared by itself when its time was up, not by an admin. */
    public function clearedAutomatically(): bool
    {
        return $this->cleared_at === null && $this->expires_at !== null && ! $this->expires_at->isFuture();
    }

    /** "24 hours" — how long every alert stays in the apps. */
    public static function clearsAfterLabel(): string
    {
        return self::CLEARS_AFTER_HOURS === 1 ? '1 hour' : self::CLEARS_AFTER_HOURS . ' hours';
    }

    /**
     * How long until it clears itself, rounded: "40 minutes", "19 hours",
     * "24 hours" (just sent). Null when it has no time or is already
     * cleared.
     */
    public function clearsIn(): ?string
    {
        if (! $this->expires_at || ! $this->isShowing()) {
            return null;
        }

        $minutes = max(1, (int) ceil(now()->diffInMinutes($this->expires_at, false)));
        if ($minutes < 60) {
            return $minutes === 1 ? '1 minute' : "{$minutes} minutes";
        }

        $hours = (int) round($minutes / 60);
        if ($hours <= 24) {
            return $hours === 1 ? '1 hour' : "{$hours} hours";
        }

        $days = (int) round($hours / 24);

        return $days === 1 ? '1 day' : "{$days} days";
    }

    /** When it left the apps; null while it's still showing. */
    public function clearedOn(): ?CarbonInterface
    {
        if ($this->cleared_at) {
            return $this->cleared_at;
        }

        return $this->clearedAutomatically() ? $this->expires_at : null;
    }

    /** "the residents' app", "the responders' app" or both. */
    public function appsLabel(): string
    {
        return match ($this->type) {
            'Both'       => "the residents' and responders' apps",
            'Responders' => "the responders' app",
            default      => "the residents' app",
        };
    }
}
