<?php

use Illuminate\Support\Facades\Route;
use Modules\Company\Models\Company;

Route::get('{any?}', function () { // if it has modules with frontend, this pattern will apply
  $company = Company::first();
  return view('app', compact('company'));
})->where('any', '^(?!api).*$');
