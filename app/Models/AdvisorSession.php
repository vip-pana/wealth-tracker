<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $kind
 * @property string|null $title
 * @property string $status
 * @property string|null $error
 * @property Carbon|null $last_read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, AdvisorMessage> $messages
 */
class AdvisorSession extends Model
{
    public const KIND_REPORT = 'report';

    public const KIND_CHAT = 'chat';

    /**
     * A chat opened explicitly to define/revise the GOAL or the risk PROFILE
     * (e.g. from the "Ridefinisci con l'AI" button or the profile starter). Only
     * these sessions may surface the "generate proposal" button — a plain chat
     * that merely mentions figures must not. Set by StartChatController from the
     * opening message; a plain chat can still be promoted at runtime when the
     * user later states the intent (see ContinueChat::interviewKind).
     */
    public const KIND_GOAL_INTERVIEW = 'goal_interview';

    public const KIND_PROFILE_INTERVIEW = 'profile_interview';

    public const STATUS_PENDING = 'pending';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    protected $fillable = ['kind', 'title', 'status', 'error', 'last_read_at'];

    /** @var array<string, string> */
    protected $casts = ['last_read_at' => 'datetime'];

    /** @return HasMany<AdvisorMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(AdvisorMessage::class, 'session_id')->orderBy('id');
    }

    /**
     * Whether this session is a goal- or profile-defining interview — the only
     * context where the advisor may offer the "generate proposal" button. Reads
     * the session kind; a plain chat is not an interview even if it discusses
     * figures. ContinueChat additionally promotes a plain chat when the user
     * states the intent mid-conversation.
     */
    public function isGoalInterview(): bool
    {
        return $this->kind === self::KIND_GOAL_INTERVIEW;
    }

    public function isProfileInterview(): bool
    {
        return $this->kind === self::KIND_PROFILE_INTERVIEW;
    }

    /**
     * Classify a message as stating the intent to define/revise the GOAL or the
     * risk PROFILE, returning the matching interview kind or null. Deliberately
     * narrow: it matches an explicit "define/revise my goal/profile/milestones"
     * phrasing, NOT the mere mention of figures or a one-off simulation question
     * ("quanto devo versare per fare un milione") — those are plain chat. Used to
     * tag a session at creation and to promote a plain chat at runtime.
     *
     * @return self::KIND_GOAL_INTERVIEW|self::KIND_PROFILE_INTERVIEW|null
     */
    public static function interviewIntentKind(string $message): ?string
    {
        $text = mb_strtolower(trim($message));

        // The verb of *defining/revising a plan*, not of asking a number.
        $intent = 'defin|ridefin|rivede|rivalut|imposta|costru|second te|aiutami a (?:defin|ridefin|rivede|imposta|costru)';

        if (preg_match('/\b(?:'.$intent.')\w*\b.{0,40}\b(?:profil)/u', $text) === 1) {
            return self::KIND_PROFILE_INTERVIEW;
        }

        if (preg_match('/\b(?:'.$intent.')\w*\b.{0,40}\b(?:obiettiv|goal|milestone|traguard|tapp)/u', $text) === 1) {
            return self::KIND_GOAL_INTERVIEW;
        }

        return null;
    }

    /**
     * A reply is still being produced when the newest message is a pending
     * assistant turn (chat) or the opening report is still generating.
     */
    public function isGenerating(): bool
    {
        if ($this->status === self::STATUS_PENDING) {
            return true;
        }

        $last = $this->messages->last();

        return $last?->role === AdvisorMessage::ROLE_ASSISTANT && $last->status === AdvisorMessage::STATUS_PENDING;
    }

    /**
     * The session has a finished assistant reply the user hasn't seen: the last
     * message is a done assistant turn newer than their last read (or never
     * read). The currently open session is marked read, so it never shows here.
     */
    public function hasUnread(): bool
    {
        $last = $this->messages->last();

        if ($last?->role !== AdvisorMessage::ROLE_ASSISTANT || $last->status !== AdvisorMessage::STATUS_DONE) {
            return false;
        }

        return $this->last_read_at === null || ($last->created_at !== null && $last->created_at->greaterThan($this->last_read_at));
    }
}
