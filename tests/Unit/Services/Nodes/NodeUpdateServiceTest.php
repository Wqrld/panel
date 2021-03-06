<?php

namespace Tests\Unit\Services\Nodes;

use Mockery as m;
use Tests\TestCase;
use phpmock\phpunit\PHPMock;
use Pterodactyl\Models\Node;
use GuzzleHttp\Psr7\Response;
use Tests\Traits\MocksRequestException;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Database\ConnectionInterface;
use Pterodactyl\Services\Nodes\NodeUpdateService;
use Pterodactyl\Contracts\Repository\NodeRepositoryInterface;
use Pterodactyl\Contracts\Repository\Daemon\ConfigurationRepositoryInterface;

class NodeUpdateServiceTest extends TestCase
{
    use PHPMock, MocksRequestException;

    /**
     * @var \Illuminate\Database\ConnectionInterface|\Mockery\Mock
     */
    private $connection;

    /**
     * @var \Pterodactyl\Contracts\Repository\Daemon\ConfigurationRepositoryInterface|\Mockery\Mock
     */
    private $configRepository;

    /**
     * @var \Pterodactyl\Contracts\Repository\NodeRepositoryInterface|\Mockery\Mock
     */
    private $repository;

    /**
     * Setup tests.
     */
    public function setUp()
    {
        parent::setUp();

        $this->connection = m::mock(ConnectionInterface::class);
        $this->configRepository = m::mock(ConfigurationRepositoryInterface::class);
        $this->repository = m::mock(NodeRepositoryInterface::class);
    }

    /**
     * Test that the daemon secret is reset when `reset_secret` is passed in the data.
     */
    public function testNodeIsUpdatedAndDaemonSecretIsReset()
    {
        $model = factory(Node::class)->make();

        $this->getFunctionMock('\\Pterodactyl\\Services\\Nodes', 'str_random')
            ->expects($this->once())->willReturn('random_string');

        $this->connection->shouldReceive('beginTransaction')->withNoArgs()->once()->andReturnNull();
        $this->repository->shouldReceive('update')->with($model->id, [
            'name' => 'NewName',
            'daemonSecret' => 'random_string',
        ])->andReturn($model);

        $this->configRepository->shouldReceive('setNode')->with($model)->once()->andReturnSelf()
            ->shouldReceive('update')->withNoArgs()->once()->andReturn(new Response);
        $this->connection->shouldReceive('commit')->withNoArgs()->once()->andReturnNull();

        $response = $this->getService()->handle($model, ['name' => 'NewName', 'reset_secret' => true]);
        $this->assertInstanceOf(Node::class, $response);
    }

    /**
     * Test that daemon secret is not modified when no variable is passed in data.
     */
    public function testNodeIsUpdatedAndDaemonSecretIsNotChanged()
    {
        $model = factory(Node::class)->make();

        $this->connection->shouldReceive('beginTransaction')->withNoArgs()->once()->andReturnNull();
        $this->repository->shouldReceive('update')->with($model->id, [
            'name' => 'NewName',
        ])->andReturn($model);

        $this->configRepository->shouldReceive('setNode')->with($model)->once()->andReturnSelf()
            ->shouldReceive('update')->withNoArgs()->once()->andReturn(new Response);
        $this->connection->shouldReceive('commit')->withNoArgs()->once()->andReturnNull();

        $response = $this->getService()->handle($model, ['name' => 'NewName']);
        $this->assertInstanceOf(Node::class, $response);
    }

    /**
     * Test that an exception caused by a connection error is handled.
     *
     * @expectedException \Pterodactyl\Exceptions\Service\Node\ConfigurationNotPersistedException
     */
    public function testExceptionRelatedToConnection()
    {
        $this->configureExceptionMock(ConnectException::class);
        $model = factory(Node::class)->make();

        $this->connection->shouldReceive('beginTransaction')->withNoArgs()->once()->andReturnNull();
        $this->repository->shouldReceive('update')->andReturn($model);

        $this->configRepository->shouldReceive('setNode->update')->once()->andThrow($this->getExceptionMock());
        $this->connection->shouldReceive('commit')->withNoArgs()->once()->andReturnNull();

        $this->getService()->handle($model, ['name' => 'NewName']);
    }

    /**
     * Test that an exception not caused by a daemon connection error is handled.
     *
     * @expectedException \Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException
     */
    public function testExceptionNotRelatedToConnection()
    {
        $this->configureExceptionMock();
        $model = factory(Node::class)->make();

        $this->connection->shouldReceive('beginTransaction')->withNoArgs()->once()->andReturnNull();
        $this->repository->shouldReceive('update')->andReturn($model);

        $this->configRepository->shouldReceive('setNode->update')->once()->andThrow($this->getExceptionMock());

        $this->getService()->handle($model, ['name' => 'NewName']);
    }

    /**
     * Return an instance of the service with mocked injections.
     *
     * @return \Pterodactyl\Services\Nodes\NodeUpdateService
     */
    private function getService(): NodeUpdateService
    {
        return new NodeUpdateService($this->connection, $this->configRepository, $this->repository);
    }
}
