<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('api/login','LoginController@login');
