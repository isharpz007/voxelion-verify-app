<?php

namespace App\Enums;

enum SessionState: string
{
    case Created = 'created';
    case Consented = 'consented';
    case IntakeDone = 'intake_done';
    case PhotoOk = 'photo_ok';
    case LiveCheck = 'live_check';
    case Verified = 'verified';
    case Failed = 'failed';
    case ManualReview = 'manual_review';
    case Withdrawn = 'withdrawn';
    case Declined = 'declined';

    /**
     * Ordered journey states — used to compute progress and to validate
     * that a state transition only moves forward (or to a terminal state).
     */
    public function order(): int
    {
        return match ($this) {
            self::Created => 0,
            self::Consented => 1,
            self::IntakeDone => 2,
            self::PhotoOk => 3,
            self::LiveCheck => 4,
            self::Verified, self::Failed, self::ManualReview,
            self::Withdrawn, self::Declined => 5,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Verified, self::Failed, self::ManualReview,
            self::Withdrawn, self::Declined,
        ], true);
    }

    public function canTransitionTo(self $next): bool
    {
        if ($this->isTerminal()) {
            return false;
        }

        return $next->order() === $this->order() + 1;
    }

    /** The journey route a session in this state should be on. */
    public function route(): string
    {
        return match ($this) {
            self::Created => '/consent',
            self::Consented => '/intake',
            self::IntakeDone => '/photo',
            self::PhotoOk, self::LiveCheck => '/live',
            self::Verified, self::Failed, self::ManualReview => '/result',
            self::Withdrawn, self::Declined => '/exit',
        };
    }
}
