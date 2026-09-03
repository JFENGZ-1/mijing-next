<?php

namespace App\Enums;

enum ExportJobType: string
{
    case MemberExport = 'member_export';
    case CardExport = 'card_export';
}
