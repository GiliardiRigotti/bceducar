<?php

namespace App\EnrollmentRequests;

enum AttendanceMode: string
{
    case Online = 'ONLINE';
    case InPerson = 'PRESENCIAL';
}
