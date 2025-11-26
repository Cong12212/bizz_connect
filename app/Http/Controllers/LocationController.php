<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\State;
use App\Models\City;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/countries",
     *     tags={"Locations"},
     *     summary="Get all countries",
     *     @OA\Response(response=200, description="List of countries")
     * )
     */
    public function countries()
    {
        return Country::orderBy('name')->get();
    }

    /**
     * @OA\Get(
     *     path="/api/countries/{code}/states",
     *     tags={"Locations"},
     *     summary="Get states by country code",
     *     @OA\Parameter(name="code", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="List of states")
     * )
     */
    public function states($countryCode)
    {
        $country = Country::where('code', $countryCode)->firstOrFail();
        return State::where('country_id', $country->id)->orderBy('name')->get();
    }

    /**
     * @OA\Get(
     *     path="/api/states/{code}/cities",
     *     tags={"Locations"},
     *     summary="Get cities by state code",
     *     @OA\Parameter(name="code", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="List of cities")
     * )
     */
    public function cities($stateCode)
    {
        $state = State::where('code', $stateCode)->firstOrFail();
        return City::where('state_id', $state->id)->orderBy('name')->get();
    }
}
