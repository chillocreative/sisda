<?php

namespace App\Http\Controllers;

use App\Exports\MulaCulaanExport;
use App\Models\DataPengundi;
use App\Models\MulaCulaan;
use Carbon\Carbon;
use DateTime;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function mulaCulaan(Request $request){
        if(!$request->from){
            $from = Carbon::now()->subDays(30)->format('Y-m-d');
            $to = Carbon::now()->addDay()->format('Y-m-d');
            return redirect('report/mula-culaan?from=' . $from . '&to=' . $to);
        }else{
            $from = Carbon::parse($request->from)->format('Y-m-d');
            $to = Carbon::parse($request->to)->addDay()->format('Y-m-d');
        }

        $mulaCulaan = MulaCulaan::whereBetween('created_at', [$from, $to])->get();
        return view('pages.report.mula-culaan', compact('mulaCulaan'));
    }

    public function exportExcelMulaCulaan(Request $request){
        $from = $request->from;
        $to = $request->to;
        $fileName = 'Mula Culaan dari ' . $from . ' hingga ' . $to;
        $data = new MulaCulaanExport($from, $to);
        return Excel::download($data, $fileName . '.xlsx');
    }

    public function dataPengundi(){
        $dataPengundi = DataPengundi::orderBy('created_at', 'DESC')->get();
        return view('pages.report.data-pengundi', compact('dataPengundi'));
    }
}
