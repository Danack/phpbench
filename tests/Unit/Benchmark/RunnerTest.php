<?php

/*
 * This file is part of the PHPBench package
 *
 * (c) Daniel Leech <daniel@dantleech.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpBench\Tests\Benchmark;

use PhpBench\Benchmark\Iteration;
use PhpBench\Benchmark\IterationResult;
use PhpBench\Benchmark\Runner;
use PhpBench\PhpBench;
use PhpBench\Tests\Util\TestUtil;
use Prophecy\Argument;

class RunnerTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->collectionBuilder = $this->prophesize('PhpBench\Benchmark\CollectionBuilder');
        $this->collection = $this->prophesize('PhpBench\Benchmark\Collection');
        $this->subject = $this->prophesize('PhpBench\Benchmark\Metadata\SubjectMetadata');
        $this->collectionBuilder->buildCollection(__DIR__, array(), array())->willReturn($this->collection);
        $this->executor = $this->prophesize('PhpBench\Benchmark\ExecutorInterface');
        $this->benchmark = $this->prophesize('PhpBench\Benchmark\Metadata\BenchmarkMetadata');

        $this->runner = new Runner(
            $this->collectionBuilder->reveal(),
            $this->executor->reveal(),
            null,
            null
        );
    }

    /**
     * It should run the tests.
     *
     * - With 1 iteration, 1 revolution
     * - With 1 iteration, 4 revolutions
     *
     * @dataProvider provideRunner
     */
    public function testRunner($iterations, $revs, array $parameters, $expected, $exception = null)
    {
        if ($exception) {
            $this->setExpectedException($exception[0], $exception[1]);
        }

        $this->collection->getBenchmarks()->willReturn(array(
            $this->benchmark,
        ));
        TestUtil::configureSubject($this->subject, array(
            'iterations' => $iterations,
            'beforeMethods' => array('beforeFoo'),
            'afterMethods' => array(),
            'parameterSets' => array(array($parameters)),
            'groups ' => array(),
            'revs' => $revs,
        ));

        TestUtil::configureBenchmark($this->benchmark);
        $this->benchmark->getSubjectMetadatas()->willReturn(array(
            $this->subject->reveal(),
        ));

        if (!$exception) {
            $this->executor->execute(Argument::type('PhpBench\Benchmark\Iteration'))
                ->shouldBeCalledTimes(count($revs) * $iterations)
                ->willReturn(new IterationResult(10, 10));
        }

        $result = $this->runner->runAll(__DIR__);

        $this->assertInstanceOf('PhpBench\Benchmark\SuiteDocument', $result);
        $this->assertEquals(
            trim(sprintf(<<<EOT
<?xml version="1.0"?>
<phpbench version="%s">
%s
</phpbench>
EOT
            , PhpBench::VERSION, $expected)),
            trim($result->dump())
        );
    }

    public function provideRunner()
    {
        return array(
            array(
                1,
                array(1),
                array(),
                <<<EOT
  <benchmark class="Benchmark">
    <subject name="benchFoo">
      <variant sleep="0">
        <iteration revs="1" time="10" memory="10" deviation="0" rejection-count="0"/>
      </variant>
    </subject>
  </benchmark>
EOT
            ),
            array(
                1,
                array(1, 3),
                array(),
                <<<EOT
  <benchmark class="Benchmark">
    <subject name="benchFoo">
      <variant sleep="0">
        <iteration revs="1" time="10" memory="10" deviation="0" rejection-count="0"/>
        <iteration revs="3" time="10" memory="10" deviation="0" rejection-count="0"/>
      </variant>
    </subject>
  </benchmark>
EOT
            ),
            array(
                4,
                array(1, 3),
                array(),
                <<<EOT
  <benchmark class="Benchmark">
    <subject name="benchFoo">
      <variant sleep="0">
        <iteration revs="1" time="10" memory="10" deviation="0" rejection-count="0"/>
        <iteration revs="3" time="10" memory="10" deviation="0" rejection-count="0"/>
        <iteration revs="1" time="10" memory="10" deviation="0" rejection-count="0"/>
        <iteration revs="3" time="10" memory="10" deviation="0" rejection-count="0"/>
        <iteration revs="1" time="10" memory="10" deviation="0" rejection-count="0"/>
        <iteration revs="3" time="10" memory="10" deviation="0" rejection-count="0"/>
        <iteration revs="1" time="10" memory="10" deviation="0" rejection-count="0"/>
        <iteration revs="3" time="10" memory="10" deviation="0" rejection-count="0"/>
      </variant>
    </subject>
  </benchmark>
EOT
            ),
            array(
                1,
                array(1),
                array('one' => 'two', 'three' => 'four'),
                <<<EOT
  <benchmark class="Benchmark">
    <subject name="benchFoo">
      <variant sleep="0">
        <parameter name="one" value="two"/>
        <parameter name="three" value="four"/>
        <iteration revs="1" time="10" memory="10" deviation="0" rejection-count="0"/>
      </variant>
    </subject>
  </benchmark>
EOT
            ),
            array(
                1,
                array(1),
                array('one', 'two'),
                <<<EOT
  <benchmark class="Benchmark">
    <subject name="benchFoo">
      <variant sleep="0">
        <parameter name="0" value="one"/>
        <parameter name="1" value="two"/>
        <iteration revs="1" time="10" memory="10" deviation="0" rejection-count="0"/>
      </variant>
    </subject>
  </benchmark>
EOT
            ),
            array(
                1,
                array(1),
                array('one' => array('three' => 'four')),
                <<<EOT
  <benchmark class="Benchmark">
    <subject name="benchFoo">
      <variant sleep="0">
        <parameter name="one" type="collection">
          <parameter name="three" value="four"/>
        </parameter>
        <iteration revs="1" time="10" memory="10" deviation="0" rejection-count="0"/>
      </variant>
    </subject>
  </benchmark>
EOT
            ),
            array(
                1,
                array(1),
                array('one' => array('three' => new \stdClass())),
                '',
                array('InvalidArgumentException', 'Parameters must be either scalars or arrays, got: stdClass'),
            ),
        );
    }

    /**
     * It should skip subjects that should be skipped.
     */
    public function testSkip()
    {
        $this->collection->getBenchmarks()->willReturn(array(
            $this->benchmark,
        ));
        TestUtil::configureSubject($this->subject, array(
            'skip' => true,
        ));
        $this->benchmark->getSubjectMetadatas()->willReturn(array(
            $this->subject->reveal(),
        ));
        TestUtil::configureBenchmark($this->benchmark);
        $result = $this->runner->runAll(__DIR__);

        $this->assertInstanceOf('PhpBench\Benchmark\SuiteDocument', $result);
        $this->assertEquals(
            trim(sprintf(<<<EOT
<?xml version="1.0"?>
<phpbench version="%s">
  <benchmark class="Benchmark">
    <subject name="benchFoo"/>
  </benchmark>
</phpbench>
EOT
            , PhpBench::VERSION)),
            trim($result->dump())
        );
    }

    /**
     * It should throw an exception if the retry threshold is not numeric.
     *
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage numeric
     */
    public function testRetryNotNumeric()
    {
        $this->runner->setRetryThreshold('asd');
    }

    /**
     * It should set the sleep attribute in the DOM.
     * It should allow the sleep value to be overridden.
     */
    public function testSleep()
    {
        $this->collection->getBenchmarks()->willReturn(array(
            $this->benchmark,
        ));
        TestUtil::configureSubject($this->subject, array(
            'sleep' => 50,
        ));
        $this->benchmark->getSubjectMetadatas()->willReturn(array(
            $this->subject->reveal(),
        ));
        TestUtil::configureBenchmark($this->benchmark);
        $this->executor->execute(Argument::type('PhpBench\Benchmark\Iteration'))
            ->shouldBeCalledTimes(2)
            ->willReturn(new IterationResult(10, 10));

        $result = $this->runner->runAll(__DIR__);

        $this->assertInstanceOf('PhpBench\Benchmark\SuiteDocument', $result);
        $this->assertEquals(
            trim(sprintf(<<<EOT
<?xml version="1.0"?>
<phpbench version="%s">
  <benchmark class="Benchmark">
    <subject name="benchFoo">
      <variant sleep="50">
        <iteration revs="1" time="10" memory="10" deviation="0" rejection-count="0"/>
      </variant>
    </subject>
  </benchmark>
</phpbench>
EOT
            , PhpBench::VERSION)),
            trim($result->dump())
        );

        $this->runner->overrideSleep(100);
        $result = $this->runner->runAll(__DIR__);
        $this->assertEquals(
            trim(sprintf(<<<EOT
<?xml version="1.0"?>
<phpbench version="%s">
  <benchmark class="Benchmark">
    <subject name="benchFoo">
      <variant sleep="100">
        <iteration revs="1" time="10" memory="10" deviation="0" rejection-count="0"/>
      </variant>
    </subject>
  </benchmark>
</phpbench>
EOT
            , PhpBench::VERSION)),
            trim($result->dump())
        );
    }

    /**
     * It should serialize the retry threshold.
     */
    public function testRetryThreshold()
    {
        $this->collection->getBenchmarks()->willReturn(array(
            $this->benchmark,
        ));
        TestUtil::configureSubject($this->subject, array(
            'sleep' => 50,
        ));
        $this->benchmark->getSubjectMetadatas()->willReturn(array(
            $this->subject->reveal(),
        ));
        TestUtil::configureBenchmark($this->benchmark);
        $this->executor->execute(Argument::type('PhpBench\Benchmark\Iteration'))
            ->shouldBeCalledTimes(1)
            ->willReturn(new IterationResult(10, 10));

        $this->runner->setRetryThreshold(10);
        $result = $this->runner->runAll(__DIR__);

        $this->assertInstanceOf('PhpBench\Benchmark\SuiteDocument', $result);
        $this->assertEquals(
            trim(sprintf(<<<EOT
<?xml version="1.0"?>
<phpbench version="%s" retry-threshold="10">
  <benchmark class="Benchmark">
    <subject name="benchFoo">
      <variant sleep="50">
        <iteration revs="1" time="10" memory="10" deviation="0" rejection-count="0"/>
      </variant>
    </subject>
  </benchmark>
</phpbench>
EOT
            , PhpBench::VERSION)),
            trim($result->dump())
        );
    }

    /**
     * It should call the before and after class methods.
     */
    public function testBeforeAndAfterClass()
    {
        TestUtil::configureBenchmark($this->benchmark, array(
            'beforeClassMethods' => array('afterClass'),
            'afterClassMethods' => array('beforeClass'),
        ));

        $this->executor->executeMethods($this->benchmark->reveal(), array('beforeClass'))->shouldBeCalled();
        $this->executor->executeMethods($this->benchmark->reveal(), array('afterClass'))->shouldBeCalled();
        $this->benchmark->getSubjectMetadatas()->willReturn(array());
        $this->collection->getBenchmarks()->willReturn(array(
            $this->benchmark,
        ));

        $this->runner->runAll(__DIR__);
    }

    private function configureSubject($subject, array $options)
    {
        $options = array_merge(array(
            'iterations' => 1,
            'name' => 'benchFoo',
            'beforeMethods' => array(),
            'afterMethods' => array(),
            'parameterSets' => array(array(array())),
            'groups' => array(),
            'revs' => 1,
            'notApplicable' => false,
            'skip' => false,
            'sleep' => 0,
        ), $options);

        $subject->getIterations()->willReturn($options['iterations']);
        $subject->getSleep()->willReturn($options['sleep']);
        $subject->getName()->willReturn($options['name']);
        $subject->getBeforeMethods()->willReturn($options['beforeMethods']);
        $subject->getAfterMethods()->willReturn($options['afterMethods']);
        $subject->getParameterSets()->willReturn($options['parameterSets']);
        $subject->getGroups()->willReturn($options['groups']);
        $subject->getRevs()->willReturn($options['revs']);
        $subject->getSkip()->willReturn($options['skip']);
    }

    private function configureBenchmark($benchmark, array $options = array())
    {
        $options = array_merge(array(
            'class' => 'Benchmark',
            'beforeClassMethods' => array(),
            'afterClassMethods' => array(),
        ), $options);

        $benchmark->getClass()->willReturn($options['class']);
        $benchmark->getBeforeClassMethods()->willReturn($options['beforeClassMethods']);
        $benchmark->getAfterClassMethods()->willReturn($options['afterClassMethods']);
    }
}

class RunnerTestBenchCase
{
}
