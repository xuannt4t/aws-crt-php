<?php

namespace App\Enums;

enum TaskActivityType: string
{
    case Created = 'created';
    case StatusChanged = 'status_changed';
    case Assigned = 'assigned';
    case ProgressUpdated = 'progress_updated';
    case QuantityUpdated = 'quantity_updated';
    case Commented = 'commented';
    case AttachmentAdded = 'attachment_added';
    case AttachmentRemoved = 'attachment_removed';
}
