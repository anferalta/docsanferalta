<?php

use App\Services\Auth;

function auth(): Auth
{
    return new Auth();
}