<?php

// SPDX-FileCopyrightText: 2026 Vincenzo Panacciulli
// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Models;

use App\Advisor\AdvisorOutputFilter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $name
 * @property Carbon|null $birth_date
 * @property string|null $risk_tolerance
 * @property int $emergency_fund_months
 * @property string|null $notes
 * @property string|null $memory
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class InvestorProfile extends Model
{
    protected $table = 'investor_profile';

    // `horizon` is deliberately absent: it is derived from the goal's target date
    // (Goal::horizon()), so it must not be mass-assigned here. The column stays in
    // place holding whatever was last hand-entered, but nothing reads it.
    protected $fillable = ['name', 'birth_date', 'risk_tolerance', 'emergency_fund_months', 'notes', 'memory'];

    /** @var array<string, string> */
    protected $casts = [
        'birth_date' => 'date',
        'emergency_fund_months' => 'integer',
    ];

    /**
     * `notes` as sent to the UI. The advisor writes this through the profile
     * proposal card, it is kept on the Goal page for good, and it is shown
     * outside the advisor with none of its framing — the same properties that
     * made saved milestones need the firewall. Screened on the way out as well
     * as on the way in, because rows written before this existed are already
     * in the database.
     */
    public function displayNotes(): ?string
    {
        return $this->screen($this->notes);
    }

    /**
     * `memory` as sent to the UI. This is the field that most needed it:
     * `remember_fact` is the only profile tool that writes with no
     * confirmation click, so the model's own words land here unreviewed, stay
     * for good, and are replayed into the briefing on every later turn.
     */
    public function displayMemory(): ?string
    {
        return $this->screen($this->memory);
    }

    private function screen(?string $value): ?string
    {
        return $value === null ? null : app(AdvisorOutputFilter::class)->filterField($value);
    }
}
