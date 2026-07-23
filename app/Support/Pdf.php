<?php

namespace App\Support;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;

/**
 * Renders a Blade view to a downloadable PDF using the dompdf library DIRECTLY,
 * not the barryvdh facade/ServiceProvider. This avoids depending on Laravel's
 * package-discovery manifest / cached service container (which can be stale on
 * shared hosting after a composer install), so PDF export works on a plain
 * deploy without a cache-clear.
 */
class Pdf
{
    /** Render a Blade view to raw PDF bytes (for streaming, base64, attachments). */
    public static function raw(string $view, array $data, string $paper = 'a4', string $orientation = 'portrait'): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        // Render temp files under storage (writable) rather than the system temp.
        $options->set('tempDir', storage_path('app'));

        $dompdf = new Dompdf($options);
        $dompdf->setPaper($paper, $orientation);
        $dompdf->loadHtml(view($view, $data)->render());
        $dompdf->render();

        return $dompdf->output();
    }

    public static function download(string $view, array $data, string $filename, string $paper = 'a4', string $orientation = 'portrait'): Response
    {
        return new Response(self::raw($view, $data, $paper, $orientation), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
