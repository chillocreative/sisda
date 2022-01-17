<!DOCTYPE html>
<html>
<head>
    <title>Akaun Diluluskan</title>
</head>

<body>
<h2>Selamat {{ $user->name }}, akaun anda diluluskan!</h2>
<br/>
Akaun anda telah berjaya diluluskan. Kini anda boleh log masuk menggunakan akaun anda. <a href="{{ env('APP_URL') }}">Masuk disini</a>
</body>

</html>