<?php
namespace Enqueue\EnqueueBundle\Tests\Functional;

use Enqueue\AmqpExt\AmqpMessage;
use Enqueue\Symfony\Client\ConsumeMessagesCommand;
use Enqueue\Test\RabbitmqManagmentExtensionTrait;
use Enqueue\EnqueueBundle\Tests\Functional\App\AmqpAppKernel;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @group functional
 */
class ConsumeMessagesCommandTest extends WebTestCase
{
    use RabbitmqManagmentExtensionTrait;

    public function setUp()
    {
        parent::setUp();

        $this->removeExchange('amqp.test');
        $this->removeQueue('amqp.app.test');

        $driver = $this->container->get('enqueue.client.driver');
        $driver->setupBroker();
    }

    public function testCouldBeGetFromContainerAsService()
    {
        $command = $this->container->get('enqueue.client.consume_messages_command');

        $this->assertInstanceOf(ConsumeMessagesCommand::class, $command);
    }

    public function testClientConsumeMessagesCommandShouldConsumeMessage()
    {
        $command = $this->container->get('enqueue.client.consume_messages_command');
        $messageProcessor = $this->container->get('test.message.processor');

        $this->getMessageProducer()->send(TestMessageProcessor::TOPIC, 'test message body');

        $tester = new CommandTester($command);
        $tester->execute([
            '--message-limit' => 2,
            '--time-limit' => 'now +10 seconds',
        ]);

        $this->assertInstanceOf(AmqpMessage::class, $messageProcessor->message);
        $this->assertEquals('test message body', $messageProcessor->message->getBody());
    }

    public function testClientConsumeMessagesFromExplicitlySetQueue()
    {
        $command = $this->container->get('enqueue.client.consume_messages_command');
        $messageProcessor = $this->container->get('test.message.processor');

        $this->getMessageProducer()->send(TestMessageProcessor::TOPIC, 'test message body');

        $tester = new CommandTester($command);
        $tester->execute([
            '--message-limit' => 2,
            '--time-limit' => 'now +10 seconds',
            'client-queue-names' => ['test'],
        ]);

        $this->assertInstanceOf(AmqpMessage::class, $messageProcessor->message);
        $this->assertEquals('test message body', $messageProcessor->message->getBody());
    }

    public function testTransportConsumeMessagesCommandShouldConsumeMessage()
    {
        $command = $this->container->get('enqueue.command.consume_messages');
        $command->setContainer($this->container);
        $messageProcessor = $this->container->get('test.message.processor');

        $this->getMessageProducer()->send(TestMessageProcessor::TOPIC, 'test message body');

        $tester = new CommandTester($command);
        $tester->execute([
            '--message-limit' => 1,
            '--time-limit' => '+10sec',
            'queue' => 'amqp.app.test',
            'processor-service' => 'test.message.processor',
        ]);

        $this->assertInstanceOf(AmqpMessage::class, $messageProcessor->message);
        $this->assertEquals('test message body', $messageProcessor->message->getBody());
    }

    private function getMessageProducer()
    {
        return $this->container->get('enqueue.client.message_producer');
    }

    /**
     * @return string
     */
    public static function getKernelClass()
    {
        include_once __DIR__.'/app/AmqpAppKernel.php';

        return AmqpAppKernel::class;
    }
}