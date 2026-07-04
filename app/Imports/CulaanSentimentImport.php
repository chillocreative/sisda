<?php

namespace App\Imports;

/**
 * Plain CSV reader for the field-canvassing sentiment exports. Only the four
 * columns we need are kept; header names are matched by alias (case/spacing/
 * punctuation-insensitive) so slight export variations and a trailing
 * capture_method column don't break parsing.
 */
class CulaanSentimentImport
{
    private array $aliases = [
        'operation_name'    => ['operationname', 'operation'],
        'voter_name'        => ['votername', 'voter', 'nama', 'namapengundi'],
        'sentiment'         => ['sentiment', 'kecenderungan'],
        'constituency_code' => ['constituencycode', 'constituency', 'kodkawasan'],
    ];

    /**
     * @return array<int, array{operation_name:string, voter_name:string, sentiment:string, constituency_code:string}>
     */
    public function read(string $path): array
    {
        $fh = fopen($path, 'r');
        if ($fh === false) {
            throw new \RuntimeException("Tidak dapat membuka fail: {$path}");
        }

        $header = fgetcsv($fh);
        if ($header === false) {
            fclose($fh);

            return [];
        }
        // Strip a UTF-8 BOM from the first header cell if present.
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);

        $map = $this->mapColumns($header);

        $rows = [];
        while (($data = fgetcsv($fh)) !== false) {
            if (count(array_filter($data, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue; // blank line
            }
            $get = fn ($field) => isset($map[$field], $data[$map[$field]]) ? trim((string) $data[$map[$field]]) : '';
            $rows[] = [
                'operation_name'    => $get('operation_name'),
                'voter_name'        => $get('voter_name'),
                'sentiment'         => $get('sentiment'),
                'constituency_code' => $get('constituency_code'),
            ];
        }
        fclose($fh);

        return $rows;
    }

    private function mapColumns(array $header): array
    {
        $normalized = [];
        foreach ($header as $i => $col) {
            $key = preg_replace('/[^a-z0-9]/', '', strtolower((string) $col));
            if ($key !== '' && ! isset($normalized[$key])) {
                $normalized[$key] = $i;
            }
        }

        $map = [];
        foreach ($this->aliases as $field => $aliases) {
            foreach ($aliases as $alias) {
                if (isset($normalized[$alias])) {
                    $map[$field] = $normalized[$alias];
                    break;
                }
            }
        }

        return $map;
    }
}
