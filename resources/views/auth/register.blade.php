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
      .version{
        position: absolute;
        bottom: 0;
        right: 0;
      }

    .bg-body{
        background: #00b4db; /* fallback for old browsers */
        background: -webkit-linear-gradient(
            to left,
            #00b4db,
            #0083b0
        ); /* Chrome 10-25, Safari 5.1-6 */
        background: linear-gradient(
            to left,
            #00b4db,
            #0083b0
        ); /* W3C, IE 10+/ Edge, Firefox 16+, Chrome 26+, Opera 12+, Safari 7+ */
      }
         
      .bg-linear {
        background-image: linear-gradient(to right, #00c6ff 0%, #0072ff  51%, #00c6ff  100%)
      }
      .bg-linear {
        text-transform: uppercase;
        transition: 0.5s;
        background-size: 200% auto;
        color: white;            
      }

      .bg-linear:hover {
        background-position: right center; /* change the direction of the change here */
        color: #fff;
        text-decoration: none;
      }
    </style>

    <title>Login - Sistem Data Pengundi</title>
  </head>
  <body>

    <div class="d-flex align-items-center vh-100 bg-body">
      <div class="container">
        <div class="login mb-5">
          <div class="row justify-content-center">
            <div class="col-lg-4">
              <div class="bg-linear shadow-lg p-5" style="width: 100%; height: 100%">
                <h1 class="title text-light">
                  REGISTER
                  <hr class="my-3">
                  Sistem Data Pengundi
                </h1>
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
                          <label for="name" class="form-control-label">Name</label>
                          <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}">
                          @error('name') <small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                        <div class="form-group mt-3">
                          <label for="no_kad" class="form-control-label">Username</label>
                          <input type="text" name="no_kad" id="username" class="form-control @error('no_kad') is-invalid @enderror" placeholder="No Kad Pengenalan" value="{{ old('no_kad') }}">
                          @error('no_kad') <small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                        <div class="form-group mt-3">
                          <label for="email" class="form-control-label">Email</label>
                          <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}">
                          @error('email') <small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                        <div class="form-group mt-3">
                          <label for="phone" class="form-control-label">No Telefon</label>
                          <input type="number" name="phone" id="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}">
                          @error('phone') <small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                        <div class="form-group mt-3">
                          <label for="password" class="form-control-label">Password</label>
                          <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror">
                          @error('password') <small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                        <div class="form-group mt-3">
                          <label for="password_confirmation" class="form-control-label">Password Confirmation</label>
                          <input type="password" name="password_confirmation" id="password_confirmation @error('password_confirmation') is-invalid @enderror" class="form-control">
                          @error('password_confirmation') <small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                        <div class="form-group mt-3 text-center">
                          <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-outline-dark btn-light btn-block">REGISTER</button>
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
                      <a href="{{ route('login') }}" class="register">Login</a>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="version bg-danger vw-100">
        <div class="row justify-content-end m-0">
          <div class="col-lg-6 mr-3 text-end text-light">
            Sistem Culaan Ver. 1.0
          </div>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>
  </body>
</html>