<?php

namespace App\Enums;

enum OrderAmountCorrectionType: string
{
    case Correction = 'correction';
    case Reversal = 'reversal';
}
