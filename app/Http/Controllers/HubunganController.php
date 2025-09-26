<?php

namespace App\Http\Controllers;

use App\Models\Hubungan;
use Illuminate\Http\Request;

class HubunganController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $hubungan = Hubungan::all();
        return view('pages.hubungan.index', compact('hubungan'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:hubungan'
        ]);
        Hubungan::create($request->all());
        return back()->with('success', 'Hubungan berjaya ditambahkan');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $hubungan = Hubungan::findOrFail($id);
        return view('pages.hubungan.edit', compact('hubungan'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $hubungan = Hubungan::findOrFail($id);
        $request->validate([
            'name' => 'required|unique:hubungan,name,' . $hubungan->id
        ]);
        $hubungan->update($request->all());
        return back()->with('success', 'Hubungan berjaya diupdate');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $hubungan = Hubungan::findOrFail($id);
        $hubungan->delete();
        return back()->with('success', 'Hubungan berjaya dipadam');
    }
}
