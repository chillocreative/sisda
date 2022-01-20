<?php

namespace App\Http\Controllers;

use App\Models\Negeri;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NegeriController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $negeri = Negeri::all();
        return view('pages.negeri.index', compact('negeri'));
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
            'name' => 'required|unique:negeri',
        ]);

        Negeri::create($request->all());
        return back()->with('success', 'Negeri berjaya ditambahkan');
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
        $negeri = Negeri::findOrFail($id);
        return view('pages.negeri.edit', compact('negeri'));
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
        $negeri = Negeri::find($id);
        $request->validate([
            'name' => 'required|unique:negeri,name,' . $negeri->id,
        ]);
        $negeri->update($request->all());
        return back()->with('success', 'Negeri berjaya diupdate');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $negeri = Negeri::findOrFail($id);
        if($negeri->bandar->count() < 1){
            $negeri->delete();
            return back()->with('success', 'Negeri berjaya dihapus');
        }
        return back()->with('error', 'Negeri tak bisa dihapus sebab ada Bandar');
    }
}
