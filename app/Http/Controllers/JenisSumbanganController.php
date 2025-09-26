<?php

namespace App\Http\Controllers;

use App\Models\JenisSumbangan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JenisSumbanganController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $jenisSumbangan = JenisSumbangan::all();
        return view('pages.jenis-sumbangan.index', compact('jenisSumbangan'));
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
            'name' => 'required|unique:jenis_sumbangan',
        ]);
        JenisSumbangan::create($request->all());
        return back()->with('success', 'Jenis Sumbangan berjaya ditambahkan');
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
        $jenisSumbangan = JenisSumbangan::findOrFail($id);
        return view('pages.jenis-sumbangan.edit', compact('jenisSumbangan'));
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
        $jenisSumbangan = JenisSumbangan::findOrFail($id);
        $request->validate([
            'name' => ['required', Rule::unique('jenis_sumbangan')->ignore($jenisSumbangan->id, 'id')],
        ]);
        $jenisSumbangan->update($request->all());
        return back()->with('success', 'Jenis Sumbangan berjaya diupdate');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $jenisSumbangan = JenisSumbangan::findOrFail($id);
        $jenisSumbangan->delete();
        return back()->with('success', 'Jenis Sumbangan berhasil dipadam');
    }
}
