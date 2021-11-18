<?php

namespace Simplex;

use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\{Request, Response, RequestStack};
use Symfony\Component\HttpKernel\Controller\{ArgumentResolver, ControllerResolver};
use Symfony\Component\HttpKernel\EventListener\{ErrorListener, ResponseListener, RouterListener};
use Symfony\Component\HttpKernel\{HttpKernel, HttpKernelInterface};
use Symfony\Component\Routing\{Matcher\UrlMatcher, RequestContext, RouteCollection};

class Framework extends HttpKernel
{
    public function __construct(RouteCollection $routes)
    {
        $context = new RequestContext();
        $matcher = new UrlMatcher($routes, $context);
        $requestStack = new RequestStack();

        $controllerResolver = new ControllerResolver();
        $argumentResolver = new ArgumentResolver();

        $dispatcher = new EventDispatcher();

        $dispatcher->addSubscriber(new ContentLengthListener());
        $dispatcher->addSubscriber(new GoogleListener());

        $dispatcher->addSubscriber(new ErrorListener(fn (FlattenException $exception): Response => (
            new Response("Something went wrong! ({$exception->getMessage()})", $exception->getStatusCode())
        )));
        $dispatcher->addSubscriber(new RouterListener($matcher, $requestStack));
        $dispatcher->addSubscriber(new ResponseListener('UTF-8'));
        $dispatcher->addSubscriber(new StringResponseListener());

        parent::__construct($dispatcher, $controllerResolver, $requestStack, $argumentResolver);
    }

    public function handle(
        Request $request,
        int $type = HttpKernelInterface::MAIN_REQUEST,
        bool $catch = true
    ): Response {
        $response = parent::handle($request, $type, $catch);

        // dispatch a response event
        $this->dispatcher->dispatch(new ResponseEvent($response, $request), 'response');

        return $response;
    }
}
