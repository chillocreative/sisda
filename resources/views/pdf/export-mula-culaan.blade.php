<html>
<head>
	<title>Export PDF Mula Culaan</title>
</head>
<style>	
	table, th, td {
		border: 1px solid black;
		border-collapse: collapse;
		text-align: left;
		padding: 10px;
		font-size: 9pt;
	}
</style>
<body>	

					<div>
						<h1>Mula Culaan</h1>
						<h2>{{ Request::get('from') }} - {{ Request::get('to') }} | Total : {{ $data->count() }}</h2>
					</div>
					@foreach($data as $d)
						<h3 style="margin-top: 20px; margin-bottom: 0;">No : {{ $loop->iteration }}</h3>
						<table>
							<tr>
								<th>Nama</th>
								<td>{{ $d->nama }}</td>
							</tr>
							<tr>
								<th>No Kad</th>
								<td>{{ $d->no_kad }}</td>
							</tr>
							<tr>
								<th>Umur</th>
								<td>{{ $d->umur }}</td>
							</tr>
							<tr>
								<th>Tel</th>
								<td>{{ $d->no_telp }}</td>
							</tr>
							<tr>
								<th>Bangsa</th>
								<td>{{ $d->bangsa }}</td>
							</tr>
							<tr>
								<th>Alamat</th>
								<td>{{ $d->alamat }}</td>
							</tr>
							<tr>
								<th>Alamat 2</th>
								<td>{{ $d->alamat_2 }}</td>
							</tr>
							<tr>
								<th>Bangsa</th>
								<td>{{ $d->bangsa }}</td>
							</tr>
							<tr>
								<th>Negeri</th>
								<td>{{ $d->negeri }}</td>
							</tr>
							<tr>
								<th>Bandar</th>
								<td>{{ $d->bandar }}</td>
							</tr>
							<tr>
								<th>Kadun</th>
								<td>{{ $d->kadun }}</td>
							</tr>
							<tr>
								<th>MPKK</th>
								<td>{{ $d->mpkk }}</td>
							</tr>
							<tr>
								<th>Bilangan Isi Rumah</th>
								<td>{{ $d->bilangan_isi_rumah }}</td>
							</tr>
							<tr>
								<th>Pendapatan Isi Rumah</th>
								<td>{{ $d->jumlah_pendapatan_isi_rumah }}</td>
							</tr>
							<tr>
								<th>Pekerjaan</th>
								<td>{{ $d->pekerjaan }}</td>
							</tr>
							<tr>
								<th>Pemilik Rumah</th>
								<td>{{ $d->pemilik_rumah }}</td>
							</tr>
							<tr>
								<th>Jenis Sumbangan</th>
								<td>{{ $d->jenis_sumbangan }}</td>
							</tr>
							<tr>
								<th>Tujuan Sumbangan</th>
								<td>{{ $d->tujuan_sumbangan }}</td>
							</tr>
							<tr>
								<th>Bantuan Lain</th>
								<td>{{ $d->bantuan_lain }}</td>
							</tr>
							<tr>
								<th>Keahlian Parti</th>
								<td>{{ $d->keahlian_partai }}</td>
							</tr>
							<tr>
								<th>Kecenderungan Politik</th>
								<td>{{ $d->kecenderungan_politik }}</td>
							</tr>
							<tr>
								<th>Nota</th>
								<td>{{ $d->nota }}</td>
							</tr>
							<tr>
								<th>Tarikh dan Masa</th>
								<td>{{ $d->tarikh_dan_masa }}</td>
							</tr>
							<tr>
								<th>Created At</th>
								<td>{{ $d->created_at }}</td>
							</tr>
						</table>
					@endforeach
				</tbody>
			</table>
		</div>
	</div>
</body>
</html>