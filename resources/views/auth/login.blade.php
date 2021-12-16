<!doctype html>
<html lang="en">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">

    <style>
      a.register{
        text-decoration: none;
        color: #444;
      }
      .version{
        position: absolute;
        bottom: 0;
        right: 0;
      }
      @media only screen and (min-width: 1200px){
        h1{
          font-size: 90px;
        }
      }
    </style>

    <title>Login - Sistem Data Pengundi</title>
  </head>
  <body>

    <div class="d-flex align-items-center vh-100 bg-info">
      <div class="container">
        <div class="login text-center">
          <h1>Sistem Data Pengundi</h1>
          @if(session('error'))
            <div class="row justify-content-center mt-5">
              <div class="col-md-8">
                <div class="alert alert-danger" role="alert">
                  {{ session('error') }}
                </div>
              </div>
            </div>
          @endif
          <form action="{{ route('login') }}" method="post" class="mt-5">
          @csrf
            <div class="row form-group align-items-center justify-content-center">
              <div class="col-sm-2 text-sm-end">
                <label for="no_kad">Username</label>
              </div>
              <div class="col-md-3">
                <input type="text" name="no_kad" id="no_kad" class="form-control" placeholder="No Kad Pengenalan">
              </div>
            </div>
            <div class="row form-group align-items-center justify-content-center mt-3">
              <div class="col-sm-2 text-sm-end">
                <label for="password">Password</label>
              </div>
              <div class="col-md-3">
                <input type="password" name="password" id="password" class="form-control" placeholder="xxxxxxxxxx">
              </div>
            </div>
            <div class="row justify-content-center mt-5">
              <div class="col-5 col-lg-2 text-end">
                <div class="d-grid gap-2">
                  <input type="submit" value="Login" class="btn btn-outline-dark btn-light btn-md-lg">
                </div>
              </div>
              <div class="col-5 col-lg-2 text-start">
                <div class="d-grid gap-2">
                  <a href="" class="btn btn-outline-dark btn-light btn-md-lg">Forget Password</a>
                </div>
              </div>
            </div>
          </form>
          <div class="row mt-3">
            <div class="col-lg-12">
              <a href="" class="register">Register</a>
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