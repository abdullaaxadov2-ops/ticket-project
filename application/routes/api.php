<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\UserController;

Route::get("/health", HealthController::class);

Route::prefix("/auth")
    ->as("auth.")
    ->group(function () {
        Route::post("/register", [AuthController::class, "register"])
            ->name("register")
            ->middleware(["throttle:reg"]);
        Route::post("/login", [AuthController::class, "login"])
            ->name("login")
            ->middleware(["throttle:login"]);
        Route::post("verify", [AuthController::class, "verifyEmail"])
            ->name("verify");
        Route::post("/logout", [AuthController::class, "logout"])
            ->name("logout")
            ->middleware("auth:sanctum");
    });

Route::middleware("auth:sanctum")->group(function () {
    Route::get("/me", [ProfileController::class, "me"]);
    Route::patch("/me/password", [ProfileController::class, "changePassword"]);
    Route::patch("/me/name", [ProfileController::class, "changeProfileName"]);
    Route::patch("/admin/users/{user}/role", [UserController::class, "changeRole"]);
    Route::patch("/admin/users/{user}/block", [UserController::class, "changeBlockStatus"]);
});
