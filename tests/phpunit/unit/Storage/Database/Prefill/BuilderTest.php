<?php

namespace Bolt\Tests\Storage\Database\Prefill;

use Bolt\Storage\Database\Prefill;
use Bolt\Storage\EntityManager;
use Bolt\Storage\Repository\ContentRepository;
use Doctrine\DBAL\Exception\TableNotFoundException;
use GuzzleHttp\Exception\RequestException;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * Tests for \Bolt\Storage\Database\Prefill\Builder
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class BuilderTest extends TestCase
{
    /** @var EntityManager|ObjectProphecy */
    protected $em;
    /** @var Prefill\RecordContentGenerator|ObjectProphecy */
    protected $generator;
    /** @var callable */
    protected $generatorFactory;

    public function setUp()
    {
        $this->em = $this->prophesize(EntityManager::class);
        $this->generator = $this->prophesize(Prefill\RecordContentGenerator::class);
        $this->generatorFactory = function () {
            return $this->generator->reveal();
        };
    }

    public function testTableNotFound()
    {
        $this->em
            ->getRepository('drop_bears')
            ->willThrow(TableNotFoundException::class)
        ;

        $builder = new Prefill\Builder($this->em->reveal(), $this->generatorFactory, 5);
        $builder->build(['drop_bears'], 5);
    }

    public function testBuild()
    {
        $repo = $this->prophesize(ContentRepository::class);
        $repo->count()->willReturn(0);

        $this->em
            ->getRepository('pages')
            ->willReturn($repo)
        ;

        $expected = [
            'Kenny does Alice Springs',
            'Up the gum tree without a paddle',
        ];
        $this->generator
            ->generate(5)
            ->willReturn($expected)
        ;

        $builder = new Prefill\Builder($this->em->reveal(), $this->generatorFactory, 5);
        $result = $builder->build(['pages'], 5);

        $this->assertSame($expected, $result['created']['pages']);
    }

    public function testBuildCustomGenerator()
    {
        $repo = $this->prophesize(ContentRepository::class);
        $repo->count()->willReturn(0);

        $this->em
            ->getRepository('pages')
            ->willReturn($repo)
        ;

        $expected = [
            'Kenny does Alice Springs',
            'Up the gum tree without a paddle',
        ];
        $this->generator
            ->generate(5)
            ->willReturn($expected)
        ;
        $builder = new Prefill\Builder($this->em->reveal(), $this->generatorFactory, 5);

        $customExpected = [
            'Two koalas and a dropbear',
            'How ya goin',
        ];
        $customGenerator = function () use ($customExpected) {
            $recordGen = $this->prophesize(Prefill\RecordContentGenerator::class);
            $recordGen
                ->generate(5)
                ->willReturn($customExpected)
            ;

            return $recordGen->reveal();
        };
        $builder->setGeneratorFactory($customGenerator);

        $result = $builder->build(['pages'], 5);

        $this->assertSame($customExpected, $result['created']['pages']);
    }

    public function testApiResponseTimeout()
    {
        $repo = $this->prophesize(ContentRepository::class);
        $repo->count()->willReturn(0);

        $this->em
            ->getRepository('pages')
            ->willReturn($repo)
        ;

        $this->generator
            ->generate(5)
            ->willThrow(RequestException::class)
        ;

        $builder = new Prefill\Builder($this->em->reveal(), $this->generatorFactory, 5);
        $result = $builder->build(['pages'], 5);

        $this->assertRegExp('/^Timeout attempting connection to the/', $result['errors']['pages']);
    }

    public function testCountExceeded()
    {
        $repo = $this->prophesize(ContentRepository::class);
        $repo->count()->willReturn(9001);

        $this->em
            ->getRepository('pages')
            ->willReturn($repo)
        ;

        $builder = new Prefill\Builder($this->em->reveal(), $this->generatorFactory, 5);
        $result = $builder->build(['pages'], 5);

        $this->assertRegExp('/(pages).+(already has records)/', $result['errors']['pages']);
    }

    public function testMaxCount()
    {
        $builder = new Prefill\Builder($this->em->reveal(), $this->generatorFactory, 21);
        $this->assertSame(21, $builder->getMaxCount());

        $builder->setMaxCount(42);
        $this->assertSame(42, $builder->getMaxCount());
    }
}
