<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Auth::routes();

Route::get('/home', 'HomeController@index')->name('home');


Route::post('/contacts','ContactsController@send')->name('contacts.submit');
Route::get('/contacts','ContactsController@index')->name('contacts');
Route::get('/p/{item}','PageController@show')->name('page');
