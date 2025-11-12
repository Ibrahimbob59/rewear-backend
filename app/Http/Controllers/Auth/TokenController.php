<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TokenController extends Controller
{
    public function revoke(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Tokens revoked successfully']);
    }
}
