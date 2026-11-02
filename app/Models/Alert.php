<?php

namespace App\Models;

use App\Services\SmsService;
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
 *
 * Item 56: every broadcast is texted too, to the same audience — residents
 * MDRRMO verified and active responders (smsNumbers()). How it went is kept
 * in sms_sent, sms_failed, sms_credits and sms_note (smsState()).
 */
class Alert extends Model
{
    /** Every alert clears from the apps by itself this long after it's sent. */
    public const CLEARS_AFTER_HOURS = 24;

    protected $fillable = [
        'title', 'subtitle', 'body', 'type', 'user_id',
        'expires_at', 'cleared_at', 'cleared_by',
        'sms_sent', 'sms_failed', 'sms_credits', 'sms_note',
    ];

    protected $casts = [
        'expires_at'  => 'datetime',
        'cleared_at'  => 'datetime',
        'sms_sent'    => 'integer',
        'sms_failed'  => 'integer',
        'sms_credits' => 'integer',
    ];

    // How it went by text stays on the admin pages.
    protected $hidden = ['sms_sent', 'sms_failed', 'sms_credits', 'sms_note'];

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

    // ── Texted too (item 56) ──────────────────────────────────────────

    /**
     * The numbers a broadcast to $type (Citizens, Responders or Both) is
     * texted to: residents MDRRMO verified — never a blocked account — and
     * active responders. Each number once, as 639XXXXXXXXX; a number that
     * isn't a PH mobile number is left out.
     *
     * @return list<string>
     */
    public static function smsNumbers(string $type): array
    {
        $mobiles = collect();

        if (in_array($type, ['Citizens', 'Both'], true)) {
            $mobiles = $mobiles->merge(
                Citizen::where('verification_status', 'verified')->notBlocked()->pluck('mobile')
            );
        }

        if (in_array($type, ['Responders', 'Both'], true)) {
            $mobiles = $mobiles->merge(Responder::where('status', 'active')->pluck('mobile'));
        }

        return $mobiles
            ->map(fn ($mobile) => SmsService::normalizeNumber($mobile))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** Who's texted, for the page: "verified residents", "active responders" or both. */
    public static function smsAudience(string $type): string
    {
        return match ($type) {
            'Both'       => 'verified residents and active responders',
            'Responders' => 'active responders',
            default      => 'verified residents',
        };
    }

    /**
     * How it went by text:
     *   sent     to every number
     *   partial  to some; sms_note says why not the rest
     *   failed   to none; sms_note says why
     *   none     no one in the audience has a mobile number
     *   off      texting was off on the server
     *   null     sent before broadcasts were texted
     */
    public function smsState(): ?string
    {
        if ($this->sms_sent === null) {
            return $this->sms_note !== null ? 'off' : null;
        }

        if ($this->smsTotal() === 0) {
            return 'none';
        }

        if ($this->sms_sent === 0) {
            return 'failed';
        }

        return $this->sms_failed > 0 ? 'partial' : 'sent';
    }

    /** The numbers it was to be texted to. */
    public function smsTotal(): int
    {
        return (int) $this->sms_sent + (int) $this->sms_failed;
    }

    /**
     * How it went by text, in a sentence for the notice after sending and
     * the Audit Log. Null for a broadcast from before item 56.
     */
    public function smsLine(): ?string
    {
        $sent  = number_format((int) $this->sms_sent);
        $total = number_format($this->smsTotal());
        $what  = $this->smsTotal() === 1 ? 'mobile number' : 'mobile numbers';

        return match ($this->smsState()) {
            'sent'    => "Texted to {$sent} {$what}.",
            'partial' => "Texted to {$sent} of {$total} {$what}. {$this->sms_note}",
            'failed'  => "The text didn't go out. {$this->sms_note}",
            'none'    => $this->sms_note,
            'off'     => 'Not texted: ' . lcfirst((string) $this->sms_note),
            default   => null,
        };
    }

    /** A few words for Active Alerts: "Texted to 523", "Text not sent". */
    public function smsShort(): ?string
    {
        return match ($this->smsState()) {
            'sent'    => 'Texted to ' . number_format((int) $this->sms_sent),
            'partial' => 'Texted to ' . number_format((int) $this->sms_sent) . ' of ' . number_format($this->smsTotal()),
            'failed'  => 'Text not sent',
            'none'    => 'No one to text',
            'off'     => 'Not texted',
            default   => null,
        };
    }
}
