<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::RESPONSE)]
class PrivateNetworkAccessListener
{
    public function __invoke(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        if ($request->headers->has('Access-Control-Request-Private-Network')) {
            $response->headers->set('Access-Control-Allow-Private-Network', 'true');
        }
    }
}
// listener qui répond au header Access-Control-Request-Private-Network pour permettre les requêtes CORS depuis des réseaux privés (ex: localhost)