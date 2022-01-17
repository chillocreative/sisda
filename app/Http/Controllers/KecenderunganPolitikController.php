<?php

namespace App\Http\Controllers;

use App\Models\KecenderunganPolitik;
use Illuminate\Http\Request;

class KecenderunganPolitikController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $kecenderunganPolitik = KecenderunganPolitik::all();
        return view('pages.kecenderungan-politik.index', compact('kecenderunganPolitik'));
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
            'name' => 'required|unique:kecenderungan_politik'
        ]);
        KecenderunganPolitik::create($request->all());
        return back()->with('success', 'Kecenderungan Politik berjaya ditambahkan');
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
        //
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
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $kecenderunganPolitik = KecenderunganPolitik::findOrFail($id);
        $kecenderunganPolitik->delete();
        return back()->with('success', 'Kecenderungan Politik berjaya dihapus');
    }
}
