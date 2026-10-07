<?php

namespace App\Http\Controllers;

use App\Geo\GeocodingService;
use Illuminate\Http\Request;
use Throwable;

class GeocodingController extends Controller
{
    public function search(Request $request, GeocodingService $service)
    {
        $input = $request->validate(['address' => 'required|string|min:3|max:400']);
        try {
            $result = $service->geocode($input['address']);

            return response()->json(['result' => $result], $result ? 200 : 404)->header('Cache-Control', 'no-store');
        } catch (Throwable $error) {
            // Do not log residential addresses or provider response bodies.
            return response()->json(['message' => 'Busca indisponível. O endereço informado é suficiente para continuar.'], 503);
        }
    }
}
