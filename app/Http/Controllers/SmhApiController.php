<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Exceptions\SmhAPIException;

class SmhApiController extends Controller {

    //Throw exception if no route is found
    public function notFound() {
        throw new SmhAPIException('endpoint_not_found');
    }

}
