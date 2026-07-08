<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EvacuationCenter;

class EvacuationCenterController extends Controller
{
    /**
     * Public — guests can view evacuation centers without logging in,
     * same as the "Evacuation Centers" tile on the app's home screen.
     */
    public function index()
    {
        return response()->json(
            EvacuationCenter::orderBy('name')->get()
        );
    }
}