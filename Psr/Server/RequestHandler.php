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

namespace Vection\Component\Http\Psr\Server;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

/**
 * Class RequestHandler
 *
 * @package Vection\Component\Http\Psr\Server
 *
 * @author  David M. Lung <vection@davidlung.de>
 */
class RequestHandler implements RequestHandlerInterface
{
    /** @var MiddlewareInterface[] */
    protected array $middleware;

    /**
     * RequestHandler constructor.
     *
     * @param array $middleware
     */
    public function __construct(array $middleware = [])
    {
        foreach ( $middleware as $handler ) {
            $this->addMiddleware($handler);
        }
    }

    /**
     * @param MiddlewareInterface $middleware
     */
    public function addMiddleware(MiddlewareInterface $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    /**
     * Handles a request and produces a response.
     *
     * May call other collaborating code to generate the response.
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ( $middleware = current($this->middleware) ) {
            next($this->middleware);
            return $middleware->process($request, $this);
        }

        # None the middleware have returned a response, so there is no processed content
        throw new RuntimeException(
            'HTTP-Kernel: Expects exact one middleware that returns an response, none one does.'
        );
    }
}
