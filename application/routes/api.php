<?php

use App\Http\Controllers\Admin\UserBlockController;
use App\Http\Controllers\Admin\UserListController;
use App\Http\Controllers\Admin\UserRoleChangeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Category\CategoryCreationController;
use App\Http\Controllers\Category\CategoryDeletionController;
use App\Http\Controllers\Category\CategoryListController;
use App\Http\Controllers\Category\CategoryUpdateController;
use App\Http\Controllers\Event\EventCancellationController;
use App\Http\Controllers\Event\EventCreationController;
use App\Http\Controllers\Event\EventDeletionController;
use App\Http\Controllers\Event\EventListController;
use App\Http\Controllers\Event\EventPublicationController;
use App\Http\Controllers\Event\EventShowController;
use App\Http\Controllers\Event\EventUpdateController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Order\OrderCreationController;
use App\Http\Controllers\Order\OrderListController;
use App\Http\Controllers\Order\OrderShowController;
use App\Http\Controllers\Payment\PaymentCreationController;
use App\Http\Controllers\Payment\PaymentWebhookController;
use App\Http\Controllers\Profile\NameChangeController;
use App\Http\Controllers\Profile\PasswordChangeController;
use App\Http\Controllers\Profile\ProfileShowController;
use App\Http\Controllers\TicketType\TicketTypeCreationController;
use App\Http\Controllers\TicketType\TicketTypeDeletionController;
use App\Http\Controllers\TicketType\TicketTypeListController;
use App\Http\Controllers\TicketType\TicketTypeUpdateController;
use App\Http\Controllers\Venue\VenueCreationController;
use App\Http\Controllers\Venue\VenueDeletionController;
use App\Http\Controllers\Venue\VenueListController;
use App\Http\Controllers\Venue\VenueUpdateController;


Route::get("/health", HealthController::class);

Route::get("/categories", CategoryListController::class);
Route::get("/venues", VenueListController::class);
Route::get("/events", EventListController::class);
Route::get("/events/{event}", EventShowController::class);
Route::get("/events/{event}/ticket-types", TicketTypeListController::class);
Route::post("/payments/webhook", PaymentWebhookController::class);

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
    Route::get("/me", ProfileShowController::class);
    Route::patch("/me/password", PasswordChangeController::class);
    Route::patch("/me/name", NameChangeController::class);

    Route::get("/admin/users", UserListController::class)
        ->middleware('can:viewAny,App\Models\User');
    Route::patch("/admin/users/{user}/role", UserRoleChangeController::class)
        ->middleware('can:manage,user');
    Route::patch("/admin/users/{user}/block", UserBlockController::class)
        ->middleware('can:manage,user');

    Route::post("/categories", CategoryCreationController::class)
        ->middleware('can:create,App\Models\Category');
    Route::put("/categories/{category}", CategoryUpdateController::class)
        ->middleware('can:update,category');
    Route::delete("/categories/{category}", CategoryDeletionController::class)
        ->middleware('can:delete,category');

    Route::post("/venues", VenueCreationController::class)
        ->middleware('can:create,App\Models\Venue');
    Route::put("/venues/{venue}", VenueUpdateController::class)
        ->middleware('can:update,venue');
    Route::delete("/venues/{venue}", VenueDeletionController::class)
        ->middleware('can:delete,venue');

    Route::post("/events", EventCreationController::class)
        ->middleware('can:create,App\Models\Event');
    Route::put("/events/{event}", EventUpdateController::class)
        ->middleware('can:update,event');
    Route::delete("/events/{event}", EventDeletionController::class)
        ->middleware('can:delete,event');
    Route::post("/events/{event}/publish", EventPublicationController::class)
        ->middleware('can:publish,event');
    Route::post("/events/{event}/cancel", EventCancellationController::class)
        ->middleware('can:cancel,event');

    Route::post("/events/{event}/ticket-types", TicketTypeCreationController::class)
        ->middleware('can:create,App\Models\TicketType,event');
    Route::put("/events/{event}/ticket-types/{ticketType}", TicketTypeUpdateController::class)
        ->middleware('can:update,ticketType')
        ->scopeBindings();
    Route::delete("/events/{event}/ticket-types/{ticketType}", TicketTypeDeletionController::class)
        ->middleware('can:delete,ticketType')
        ->scopeBindings();

    Route::post("/events/{event}/orders", OrderCreationController::class)
        ->middleware('can:create,App\Models\Order');
    Route::get("/orders", OrderListController::class);
    Route::get("/orders/{order}", OrderShowController::class)
        ->middleware('can:view,order');
    Route::post("/orders/{order}/pay", PaymentCreationController::class)
        ->middleware('can:pay,order');
});
