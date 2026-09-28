<?php

namespace App\Http\Controllers;

use App\Http\Requests\VenueRequest;
use App\Models\Venue;

class VenueController extends Controller
{
    public function index()
    {
        return Venue::all();
    }

    public function store(VenueRequest $request)
    {
        $this->authorize('create', Venue::class);

        $venue = Venue::create($request->validated());

        return response()->json($venue, 201);
    }

    public function update(VenueRequest $request, Venue $venue)
    {
        $this->authorize('update', $venue);

        $venue->update($request->validated());

        return $venue;
    }

    public function destroy(Venue $venue)
    {
        $this->authorize('delete', $venue);

        $venue->delete();

        return response()->noContent();
    }
}
