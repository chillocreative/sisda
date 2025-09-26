<!doctype html>
<html lang="en">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">

    <style>
      a.register, a.forget-password{
        text-decoration: none;
        color: #444;
      }

    .bg-body{
        background-color: #00adef !important;
      }
      
      @media (min-width: 990px){
        .content {
          height: 100vh;
        }
        .version{
          position: absolute;
          bottom: 0;
          right: 0;
        }
      }
    </style>

    <title>Login - Sistem Data Pengundi</title>
  </head>
  <body>

    <div class="content d-flex align-items-center bg-body">
      <div class="container">
        <div class="login mb-5 mt-5 mt-lg-0">
          <div class="row justify-content-center">
            <div class="col-lg-4">
              <div class="shadow-lg p-5" style="width: 100%;  background-color: #203864">
                <div class="text-center">
                  <img src="{{ asset('assets/img/logo.png') }}" style="width: 200px;">
                  <h1 class="text-light" style="font-size: 70px;">SISDA</h1>
                </div>
              </div>
            </div>
            <div class="col-lg-8 mt-3 mt-lg-0">
              <div class="card border-0 rounded-0 shadow-lg">
                <div class="card-body">
                  <form action="{{ route('register') }}" method="post" class="mt-5">
                  @csrf
                    <div class="row justify-content-center">
                      <div class="col-lg-8">
                        <div class="form-group">
                          <label for="name" class="form-control-label">Nama<span class="text-danger"> *</span></label>
                          <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}">
                          @error('name') <small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                        <div class="form-group mt-3">
                          <label for="no_kad" class="form-control-label">No Kad Pengenalan<span class="text-danger"> *</span></label>
                          <input type="text" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*?)\..*/g, '$1');" onKeyPress="if(this.value.length==12) return false;" name="no_kad" id="username" class="form-control @error('no_kad') is-invalid @enderror" placeholder="No Kad Pengenalan" value="{{ old('no_kad') }}">
                          @error('no_kad') <small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                        <div class="form-group mt-3">
                          <label for="email" class="form-control-label">Alamat Email (Jika ada)</label>
                          <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}">
                          @error('email') <small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                        <div class="form-group mt-3">
                          <label for="phone" class="form-control-label">No Telefon<span class="text-danger"> *</span></label>
                          <input type="number" name="phone" id="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}">
                          @error('phone') <small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                        <div class="form-group mt-3">
                          <label for="password" class="form-control-label">Kata Laluan<span class="text-danger"> *</span></label>
                          <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror">
                          @error('password') <small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                        <div class="form-group mt-3">
                          <label for="password_confirmation" class="form-control-label">Sahkan Kata Laluan<span class="text-danger"> *</span></label>
                          <input type="password" name="password_confirmation" id="password_confirmation @error('password_confirmation') is-invalid @enderror" class="form-control">
                          @error('password_confirmation') <small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                        <div class="form-group mt-3 text-center">
                          <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary rounded-pill btn-block">DAFTAR</button>
                          </div>
                        </div>
                        @if(session('success'))
                          <div class="alert alert-success mt-3" role="alert">
                            {{ session('success') }}
                          </div>
                        @endif
                        @if(session('error'))
                          <div class="alert alert-danger mt-3" role="alert">
                            {{ session('error') }}
                          </div>
                        @endif
                        <hr>
                      </div>
                    </div>
                  </form>
                  <div class="row text-center">
                    <div class="col-lg-12">
                      <a href="{{ route('login') }}" class="register">Log Masuk</a>
                      | 
                      <a href="https://sistemdatapengundi.com" class="register">Laman Utama</a>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>
  </body>
</html>