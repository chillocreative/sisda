<?php

namespace App\Http\Controllers;

use App\Models\KeahlianPartai;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KeahlianPartaiController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $keahlianPartai = KeahlianPartai::all();
        return view('pages.keahlian-partai.index', compact('keahlianPartai'));
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
            'name' => 'required|unique:keahlian_partai'
        ]);
        KeahlianPartai::create($request->all());
        return back()->with('success', 'Keahlian Parti berjaya ditambahkan');
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
        $keahlianPartai = KeahlianPartai::findOrFail($id);
        return view('pages.keahlian-partai.edit', compact('keahlianPartai'));
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
        $keahlianPartai = KeahlianPartai::findOrFail($id);
        $request->validate([
            'name' => ['required', Rule::unique('keahlian_partai')->ignore($keahlianPartai->id, 'id')],
        ]);
        $keahlianPartai->update($request->all());
        return back()->with('success', 'Keahlian Parti berjaya diupdate');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $keahlianPartai = KeahlianPartai::findOrFail($id);
        $keahlianPartai->delete();
        return back()->with('success', 'Keahlian Parti berjaya dipadam');
    }
}
