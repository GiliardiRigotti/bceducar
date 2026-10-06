<?php

namespace App\Http\Controllers;

use iEducar\Packages\PreMatricula\Http\Controllers\AuthController;
use Illuminate\Http\Request;

class PmdSessionController extends AuthController
{
    public function login(Request $request)
    {
        // The upstream local shortcut logs everybody in as admin, defeating school isolation.
        return $this->check($request);
    }
}
