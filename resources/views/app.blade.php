<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $company->name ?? config('app.name', 'Laravel') }}</title>
        <link rel="icon" type="image/x-icon" href="{{ $company && $company->logo ? asset('storage/' . $company->logo) : asset('favicon.ico') }}">
    </head>
    <body>
        <div class="wrapper" id="app"></div>
        
        @vite(['resources/js/app.js'])
    </body>
</html>
