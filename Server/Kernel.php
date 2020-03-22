<?php

/**
 * This file is part of the Vection package.
 *
 * (c) David M. Lung <vection@davidlung.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Vection\Component\Http\Server;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Vection\Component\Http\Psr\Message\Factory\ServerRequestFactory;
use Vection\Component\Http\Server\Decorator\Factory\ServerRequestFactoryDecorator;
use Vection\Component\Http\Server\Event\AfterSendRequestEvent;
use Vection\Component\Http\Server\Event\BeforeHandleRequestEvent;
use Vection\Component\Http\Server\Event\BeforeSendRequestEvent;
use Vection\Component\Http\Server\Event\BeforeTerminateRequestEvent;
use Vection\Contracts\Event\EventDispatcherInterface;
use Vection\Contracts\Http\Server\KernelInterface;
use Vection\Contracts\Http\Server\ResponderInterface;

/**
 * Class Kernel
 *
 * @package Vection\Component\Http\Server
 *
 * @author  David M. Lung <vection@davidlung.de>
 */
class Kernel implements KernelInterface
{

    /** @var ServerRequestInterface */
    protected $request;

    /** @var RequestHandlerInterface */
    protected $requestHandler;

    /** @var ResponderInterface */
    protected $responder;

    /** @var EventDispatcherInterface */
    protected $eventDispatcher;

    /** @var LoggerInterface */
    protected $logger;

    /**
     * Kernel constructor.
     *
     * @param RequestHandlerInterface            $requestHandler
     * @param ServerRequestInterface             $request
     * @param ResponderInterface|null            $responder
     */
    public function __construct(
        RequestHandlerInterface $requestHandler,
        ? ServerRequestInterface $request = null,
        ? ResponderInterface $responder = null
    )
    {
        $this->requestHandler = $requestHandler;
        $this->request        = $request;
        $this->responder      = $responder ?: new Responder();

        if ( ! $this->request ) {
            $serverRequestFactory = new ServerRequestFactoryDecorator(new ServerRequestFactory());
            $this->request        = $serverRequestFactory->createFromGlobals();
        }
    }

    /**
     * Returns the responder that is used to send a response to the client.
     *
     * @return ResponderInterface
     */
    public function getResponder(): ResponderInterface
    {
        return $this->responder;
    }

    /**
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Sets a logger.
     *
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @param object $event
     */
    protected function fireEvent(object $event): void
    {
        if ( $this->eventDispatcher ) {
            $this->eventDispatcher->dispatch($event);
        }
    }

    /**
     * @param string $message
     */
    protected function log(string $message): void
    {
        if ( $this->logger ) {
            $this->logger->info($message);
        }
    }

    /**
     * Uses the request and response to execute the registered
     * request middleware handler and send the response to
     * the client.
     *
     * If the kernel has an event manager then the following event will be fired:
     *  - BeforeHandleRequestEvent
     *  - BeforeSendRequestEvent
     *  - AfterSendRequestEvent
     *  - BeforeTerminateRequestEvent (only if argument 1 is true)
     *
     * @param bool $terminate
     *
     *@see Kernel::setEventDispatcher()
     *
     */
    public function execute(bool $terminate = true): void
    {
        $this->log('HTTP.Kernel A new request has been received @ '.$this->request->getUri());
        $this->fireEvent(new BeforeHandleRequestEvent());

        $this->log('HTTP.Kernel execute middleware');
        $response = $this->requestHandler->handle($this->request);

        $this->fireEvent(new BeforeSendRequestEvent());

        $this->log('HTTP.Kernel.Responder start preparing response');
        $this->responder->send($response, $this->request);
        $this->log(sprintf('HTTP.Kernel.Responder response sent (%d)', $response->getStatusCode()));

        $this->fireEvent(new AfterSendRequestEvent());

        if ( $terminate ) {
            $this->fireEvent(new BeforeTerminateRequestEvent());
            $this->log('HTTP.Kernel TERMINATED');
            die(0);
        }

        $this->log('HTTP.Kernel finished execution without termination');
    }
}
