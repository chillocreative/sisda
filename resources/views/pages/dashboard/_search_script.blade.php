
<script type="text/javascript">
  const carian = $('#carian')

  carian.keyup(function () {
    const table_group = $('#table-group')
    const table_culaan = $('#table-mula-culaan tbody')
    const table_pengundi = $('#table-pengundi tbody')
    let val = this.value
    let pengundi = ''
    let culaan = ''

    if(val){
      table_group.removeClass('d-none')
    }else{
      table_group.addClass('d-none')
    }

    table_culaan.empty().append(`
      <tr>
        <td style="vertical-align: middle" colspan="23">Sila tunggu ...</td>
      </tr>
    `)
    $.ajax({
      url: '{{ route('global.mula-culaan') }}',
      method: 'POST',
      data: {
        _token: '{{ csrf_token() }}',
        no_kad: val
      },
      success: response => {
        if(response.length > 0){
          response.map((item, index) => {
            culaan += `
              <tr>
                <td style="vertical-align: middle">${index + 1}</td>
                @if(Auth::user()->role_id !== 3)<td style="vertical-align: middle">${item.user ? item.user.name : 'Deleted User'}</td>@endif
                <td style="vertical-align: middle">${item.nama}</td>
                <td style="vertical-align: middle">${item.no_kad}</td>
                <td style="vertical-align: middle">${item.umur}</td>
                <td style="vertical-align: middle">${item.no_telp}</td>
                <td style="vertical-align: middle">${item.bangsa}</td>
                <td style="vertical-align: middle">${item.alamat}</td>
                <td style="vertical-align: middle">${item.poskod}</td>
                <td style="vertical-align: middle">${item.negeri}</td>
                <td style="vertical-align: middle">${item.bandar}</td>
                <td style="vertical-align: middle">${item.kadun}</td>
                <td style="vertical-align: middle">${item.mpkk}</td>
                <td style="vertical-align: middle">${item.bilangan_isi_rumah}</td>
                <td style="vertical-align: middle">${item.jumlah_pendapatan_isi_rumah}</td>
                <td style="vertical-align: middle">${item.pekerjaan}</td>
                <td style="vertical-align: middle">${item.pemilik_rumah}</td>
                <td style="vertical-align: middle">${item.jenis_sumbangan}</td>
                <td style="vertical-align: middle">${item.tujuan_sumbangan}</td>
                <td style="vertical-align: middle">${item.bantuan_lain}</td>
                <td style="vertical-align: middle">${item.keahlian_partai}</td>
                <td style="vertical-align: middle">${item.kecenderungan_politik}</td>
                <td style="vertical-align: middle; min-width: 200px">${item.nota ?? '-'}</td>
                <td style="vertical-align: middle">${item.tarikh_dan_masa}</td>
                <td style="vertical-align: middle"><img src="{{ asset('ic') }}/${item.ic}" style="max-height: 300px;"></td>
              </tr>
            `
          })

          table_culaan.empty().append(culaan)
        }else{
          table_culaan.empty().append(`
            <tr>
              <td style="vertical-align: middle" colspan="23">Tiada Sebarang Rekod</td>
            </tr>
          `)
        }
      },
      error: err => {
        console.log(err)
      } 
    })

    table_pengundi.empty().append(`
      <tr>
        <td style="vertical-align: middle" colspan="23">Sila tunggu ...</td>
      </tr>
    `)
    $.ajax({
      url: '{{ route('global.data-pengundi') }}',
      method: 'POST',
      data: {
        _token: '{{ csrf_token() }}',
        no_kad: val
      },
      success: response => {
        if(response.length > 0){
          response.map((item, index) => {
            pengundi += `
              <tr>
                <td style="vertical-align: middle">${index + 1}</td>  
                @if(Auth::user()->role_id !== 3)<td style="vertical-align: middle">${item.user ? item.user.name : 'Deleted User'}</td>@endif
                <td style="vertical-align: middle">${item.name}</td>
                <td style="vertical-align: middle">${item.no_kad}</td>
                <td style="vertical-align: middle">${item.umur}</td>
                <td style="vertical-align: middle">${item.phone}</td>
                <td style="vertical-align: middle">${item.bangsa}</td>
                <td style="vertical-align: middle">${item.hubungan}</td>
                <td style="vertical-align: middle">${item.alamat}</td>
                <td style="vertical-align: middle">${item.poskod}</td>
                <td style="vertical-align: middle">${item.negeri}</td>
                <td style="vertical-align: middle">${item.bandar}</td>
                <td style="vertical-align: middle">${item.parlimen}</td>
                <td style="vertical-align: middle">${item.kadun}</td>
                <td style="vertical-align: middle">${item.keahlian_partai}</td>
                <td style="vertical-align: middle">${item.kecenderungan_politik}</td>
              </tr>
            `
          })

          table_pengundi.empty().append(pengundi)
        }else{
          table_pengundi.empty().append(`
            <tr>
              <td style="vertical-align: middle" colspan="15">Tiada Sebarang Rekod</td>
            </tr>
          `)
        }
      },
      error: err => {
        console.log(err)
      }
    })
  })
</script>