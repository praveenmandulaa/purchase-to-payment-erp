<?php

use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'ERP Backend Connected Successfully',
    ]);
});