<?php
/*
 * (c) Steffen Brem <steffenbrem@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Mango\Bundle\JsonApiBundle\EventListener;

use Doctrine\DBAL\Exception\ConstraintViolationException;
use Exception;
use JMS\Serializer\SerializerInterface;
use Mango\Bundle\JsonApiBundle\MangoJsonApiBundle;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Mango\Bundle\JsonApiBundle\Serializer\JsonApiResponse;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Response subscriber
 *
 * @author Sergey Chernecov <sergey.chernecov@gmail.com>
 */
class ExceptionSubscriber implements EventSubscriberInterface
{
    /**
     * Serializer
     *
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * Logger
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Enabled
     *
     * @var bool
     */
    private $enabled;

    /**
     * Exception subscriber constructor
     *
     * @param SerializerInterface $serializer
     * @param bool                $enabled
     */
    public function __construct(SerializerInterface $serializer, $enabled = false)
    {
        $this->serializer = $serializer;
        $this->enabled = $enabled;
    }

    /**
     * {@inheritdoc}
     */
    public function setLogger(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * Get subscribed events
     *
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::EXCEPTION => array('onKernelException', 0),
        ];
    }

    /**
     * On kernel exception
     *
     * @param GetResponseForExceptionEvent $event
     *
     * @return void
     */
    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        if (!$this->enabled) {
            return;
        }

        $exception = $event->getException();

        $this->logException($exception, 'Uncaught PHP Exception');

        $content = $this->serializer->serialize(
            $exception,
            MangoJsonApiBundle::FORMAT
        );

        $event->setResponse(new JsonApiResponse($content, $this->chooseResponseStatusCode($exception)));
        $event->stopPropagation();
    }

    /**
     * Logs exception
     *
     * @param Exception $exception
     * @param string    $message
     *
     * @return void
     */
    private function logException(Exception $exception, string $message)
    {
        if ($this->logger === null) {
            return;
        }

        $this->logger->critical(
            $message,
            [
                'exception'         => $exception,
                'exception_class'   => get_class($exception),
                'exception_code'    => $exception->getCode(),
                'exception_message' => $exception->getMessage(),
                'exception_file'    => $exception->getFile(),
                'exception_line'    => $exception->getLine(),
                'exception_trace'   => $exception->getTraceAsString(),
            ]
        );
    }

    /**
     * Chooses which response status code should be sent based on the exception
     *
     * @param Exception $exception
     *
     * @return int
     */
    private function chooseResponseStatusCode(Exception $exception): int
    {
        if ($exception instanceof ConstraintViolationException) {
            return JsonApiResponse::HTTP_BAD_REQUEST;
        }

        return JsonApiResponse::HTTP_INTERNAL_SERVER_ERROR;
    }
}
