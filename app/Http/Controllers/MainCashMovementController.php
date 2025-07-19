<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MainCashBalance;
use App\Models\MainCashMovement;

class MainCashMovementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->all();

        MainCashMovement::create($data);
        MainCashBalance::where('id', $request->main_cash_balance_id)->increment('current_balance', $request->amount);

        return response()->json(['success' => true], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(MainCashMovement $mainCashMovement)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(MainCashMovement $mainCashMovement)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MainCashMovement $mainCashMovement)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MainCashMovement $mainCashMovement)
    {
        //
    }
}
