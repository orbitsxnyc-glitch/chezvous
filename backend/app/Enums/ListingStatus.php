<?php

namespace App\Enums;

enum ListingStatus: string
{
    case Draft = 'draft';
    case PendingModeration = 'pending_moderation';
    case Published = 'published';
    case Rejected = 'rejected';
    case Paused = 'paused';
    case Closed = 'closed';
    case Expired = 'expired';
    case Hidden = 'hidden';
    case Archived = 'archived';
}
