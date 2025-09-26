<?php

namespace App\Http\Controllers;

use App\Models\Negeri;
use App\Models\Parlimen;
use Illuminate\Http\Request;

class ParlimenController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $negeri = Negeri::all();
        $parlimen = Parlimen::all();
        return view('pages.parlimen.index', compact('negeri', 'parlimen'));
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
            'negeri_id' => 'required',
            'code' => 'required|unique:parlimen',
            'name' => 'required|unique:parlimen',
        ]);
        Parlimen::create($request->all());
        return back()->with('success', 'Parlimen berjaya ditambahkan');
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
        $parlimen = Parlimen::findOrFail($id);
        $negeri = Negeri::all();
        return view('pages.parlimen.edit', compact('parlimen', 'negeri'));
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
        $parlimen = Parlimen::findOrFail($id);
        $request->validate([
            'negeri_id' => 'required',
            'code' => 'required|unique:parlimen,code,' . $parlimen->id,
            'name' => 'required|unique:parlimen,name,' . $parlimen->id,
        ]);
        $parlimen->update($request->all());
        return back()->with('success', 'Parlimen berjaya diupdate');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $parlimen = Parlimen::findOrFail($id);
        if($parlimen->kadun->count() < 1){
            $parlimen->delete();
            return back()->with('success', 'Parlimen berjaya dipadam');
        }
        return back()->with('error', 'Parlimen tak dapat dipadam sebab ada kadun');
    }
}
