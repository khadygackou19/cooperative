<?php

namespace App\Support;

use App\Models\JournalAction;

class Journal
{
    public static function log(?int $userId, string $action, ?string $entite = null, ?int $entiteId = null, ?string $details = null): void
    {
        JournalAction::create([
            'user_id' => $userId,
            'action' => $action,
            'entite' => $entite,
            'entite_id' => $entiteId,
            'details' => $details,
        ]);
    }
}
