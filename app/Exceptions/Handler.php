<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

class Handler extends ExceptionHandler {

    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        HttpException::class,
        SmhAPIException::class
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception) {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $e) {
        if (config('app.debug')) {
            return parent::render($request, $e);
        }
        return $this->handle($request, $e);
    }

    /**
     * Convert the Exception into a JSON HTTP Response  
     *  
     * @param Request $request  
     * @param Exception $e  
     * @return JSONResponse  
     */
    private function handle($request, Exception $e) {
        if ($e instanceOf SmhAPIException) {
            $data = $e->toArray();
            $status = $e->getStatus();
        }

        if ($e instanceOf NotFoundHttpException) {
            $data = array_merge([
                'id' => 'not_found',
                'status' => '404'
                    ], config('errors.not_found'));

            $status = 404;
        }

        if ($e instanceOf MethodNotAllowedHttpException) {
            $data = array_merge([
                'id' => 'method_not_allowed',
                'status' => '405'
                    ], config('errors.method_not_allowed'));

            $status = 405;
        }

        return response()->json($data, $status);
    }

}
