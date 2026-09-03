<?php

namespace App\Enums;

enum PayrollReportType: string
{
    case Coach = 'coach';
    case Sales = 'sales';
    case CoachCourseCommission = 'coach_course_commission';
}
