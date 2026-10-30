<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    try {
        DB::select('SELECT 1');
    } catch (QueryException) {
        return response()->json(['status' => 'unavailable', 'database' => 'disconnected'], 503);
    }

    return response()->json(['status' => 'ok', 'database' => 'connected']);
});
