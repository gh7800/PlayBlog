<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
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
     * @param  \Throwable  $exception
     * @return void
     *
     * @throws \Throwable
     */
    public function report(Throwable $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $exception)
    {
        if ($this->isApiRequest($request)) {
            if ($exception instanceof NotFoundHttpException) {
                $this->logError($request, 404, '没有找到Api');
                return error([], '没有找到Api', 404);
            } elseif ($exception instanceof AuthenticationException) {
                $this->logError($request, 401, '用户认证错误');
                return error([], '用户认证错误', 401);
            } elseif ($exception instanceof AuthorizationException) {
                $this->logError($request, 403, '权限认证错误');
                return error([], '权限认证错误', 401);
            } elseif ($exception instanceof ValidationException) {
                $message = '';
                $errors = $exception->errors();
                foreach($errors as $error) {
                    foreach ($error as $msg) {
                        $message = $message . $msg;
                    }
                }
                return error([], $message, 200);
            } else {
                $message = $exception->getMessage();
                $this->logError($request, 500, '错误信息', $exception);
                return error([], $message, 500);
            }
        } else {
            //if(! config('app.debug')) {
            if ($exception instanceof NotFoundHttpException) {
                return redirect('home');
            } elseif ($exception instanceof AuthorizationException) {
                return redirect('home');
            } elseif ($exception instanceof AuthenticationException) {
                return $this->unauthenticated($request, $exception);
            } elseif ($exception instanceof TokenMismatchException) {
                return $this->redirectToLogin();
            }
            //}
        }

        return parent::render($request, $exception);
    }

    protected function unauthenticated($request, AuthenticationException $exception)
    {
        return $request->expectsJson()
            ? error([], '用户认证错误', 401)
            : $this->redirectToLogin();
    }

    protected function redirectToLogin()
    {
        return redirect()->guest(route('login'));
    }

    private function isApiRequest($request) {
        return $request->is('api/*', 'auth/*') || $request->wantsJson();
    }

    private function logError($request, $code, $message = '', $exception = null) {
        if ($exception) {
            $message = $message . ": " . $exception->getTraceAsString();
        }
        event(new ExceptionEvent($request, $code, $message));
        //Log::error('[' . $code . ']' . $message . "\n");
    }

}
