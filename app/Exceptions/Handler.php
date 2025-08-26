<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
                return $this->error('没有找到Api');
            } elseif ($exception instanceof AuthenticationException) {
                return $this->error('用户认证错误');
            } elseif ($exception instanceof AuthorizationException) {
                return $this->error('权限认证错误');
            } elseif ($exception instanceof ValidationException) {
                $errors = $exception->errors();
                foreach($errors as $error) {
                    $message = implode('', $error);
                }
                return $this->error( $message ?? '验证错误');
            } elseif ($exception instanceof ModelNotFoundException){
                $model = class_basename($exception->getModel());
                return $this->error($model.'不存在');
            } else {
                $message = $exception->getMessage();
                return $this->error($message);
            }
        } else {
            if ($exception instanceof NotFoundHttpException) {
                return redirect('home');
            } elseif ($exception instanceof AuthorizationException) {
                return redirect('home');
            } elseif ($exception instanceof AuthenticationException) {
                return $this->unauthenticated($request, $exception);
            } elseif ($exception instanceof TokenMismatchException) {
                return $this->redirectToLogin();
            }
        }

        return parent::render($request, $exception);
    }

    protected function unauthenticated($request, AuthenticationException $exception)
    {
        return $request->expectsJson() ? $this->error('用户认证错误') : $this->redirectToLogin();
    }

    protected function redirectToLogin(): RedirectResponse
    {
        return redirect()->guest(route('login'));
    }

    private function isApiRequest($request): bool
    {
        return $request->is('api/*', 'auth/*') || $request->wantsJson();
    }

    private function error($message): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'success' => false,
            'data' => null
        ]);
    }

    /*private function logError($request, $code, $message = '', $exception = null) {
        if ($exception) {
            $message = $message . ": " . $exception->getTraceAsString();
        }
        event(new ExceptionEvent($request, $code, $message));
    }*/

}
