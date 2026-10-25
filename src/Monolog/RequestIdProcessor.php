<?php
declare(strict_types=1);

namespace App\Monolog;

use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Adds the main request's X-Request-Id header to every log record as extra.request_id,
 * so all lines logged while handling one request can be found together.
 *
 * A copy of sgoranov\IdentityLinkShared\Monolog\RequestIdProcessor, which the BFF does not
 * depend on because the shared library pulls in Doctrine, the validator and the serializer.
 */
#[AsMonologProcessor]
class RequestIdProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly RequestStack $requestStack
    )
    {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        // There is no request in console commands
        $requestId = $this->requestStack->getMainRequest()?->headers->get('X-Request-Id');

        if (null !== $requestId) {
            $record->extra['request_id'] = $requestId;
        }

        return $record;
    }
}
