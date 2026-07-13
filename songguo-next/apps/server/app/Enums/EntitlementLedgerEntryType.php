<?php

namespace App\Enums;

enum EntitlementLedgerEntryType: string
{
    case Issue = 'issue';
    case Purchase = 'purchase';
    case Recharge = 'recharge';
    case BalanceAdjust = 'balance_adjust';
    case CountDeduct = 'count_deduct';
    case CountAdjust = 'count_adjust';
    case ValidityChange = 'validity_change';
    case Freeze = 'freeze';
    case FreezeLift = 'freeze_lift';
    case HolidayApply = 'holiday_apply';
    case HolidayCancel = 'holiday_cancel';
    case Penalty = 'penalty';
    case Reversal = 'reversal';
    case Correction = 'correction';
    case Expire = 'expire';
    case Void = 'void';
    case Archive = 'archive';
    case ArchiveRestore = 'archive_restore';
    case VisibilityChange = 'visibility_change';
}
