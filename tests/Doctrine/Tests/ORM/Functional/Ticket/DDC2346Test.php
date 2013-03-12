<?php

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Logging\DebugStack;

/**
 * @group DDC-2346
 */
class DDC2346Test extends \Doctrine\Tests\OrmFunctionalTestCase
{
    /**
     * @var \Doctrine\DBAL\Logging\DebugStack
     */
    protected $logger;

    /**
     * {@inheritDoc}
     */
    protected function setup()
    {
        parent::setup();

        $this->_schemaTool->createSchema(array(
            $this->_em->getClassMetadata(__NAMESPACE__ . '\DDC2346Foo'),
            $this->_em->getClassMetadata(__NAMESPACE__ . '\DDC2346Bar'),
        ));

        $this->logger = new DebugStack();
    }

    /**
     * Verifies that fetching a OneToMany association with fetch="EAGER" does not cause N+1 queries
     */
    public function testIssue()
    {
        $foo1        = new DDC2346Foo();
        $foo2        = new DDC2346Foo();

        $bar1        = new DDC2346Bar();
        $bar2        = new DDC2346Bar();
        $bar3        = new DDC2346Bar();
        $bar4        = new DDC2346Bar();

        $bar1->foo   = $foo1;
        $bar2->foo   = $foo1;
        $bar3->foo   = $foo2;
        $bar4->foo   = $foo2;

        $foo1->bars[] = $bar1;
        $foo1->bars[] = $bar2;
        $foo2->bars[] = $bar3;
        $foo2->bars[] = $bar4;

        $this->_em->persist($foo1);
        $this->_em->persist($foo2);
        $this->_em->persist($bar1);
        $this->_em->persist($bar2);
        $this->_em->persist($bar3);
        $this->_em->persist($bar4);

        $this->_em->flush();
        $this->_em->clear();

        $this->_em->getConnection()->getConfiguration()->setSQLLogger($this->logger);

        $fetchedFoos = $this->_em->getRepository(__NAMESPACE__ . '\\DDC2346Foo')->findAll();
        //$fetchedBars = $this->_em->getRepository(__NAMESPACE__ . '\\DDC2346Bar')->findAll();

        $this->assertCount(2, $fetchedFoos);
        $this->assertCount(2, $fetchedFoos[0]->bars);
        $this->assertCount(2, $fetchedFoos[1]->bars);
        $this->assertCount(4, $fetchedBars);

        $this->assertCount(2, $this->logger->queries, 'The total number of executed queries is 2, and not n+1');
    }
}

/** @Entity */
class DDC2346Foo
{
    /** @Id @Column(type="integer") @GeneratedValue */
    public $id;

    /**
     * @var DDC2346Bar[]|\Doctrine\Common\Collections\Collection
     *
     * @OneToMany(targetEntity="DDC2346Bar", mappedBy="foo", fetch="EAGER")
     */
    public $bars;

    /** Constructor */
    public function __construct() {
        $this->bars = new ArrayCollection();
    }
}

/**
 * @Entity
 */
class DDC2346Bar
{
    /** @Id @Column(type="integer") @GeneratedValue */
    public $id;

    /** @ManyToOne(targetEntity="DDC2346Foo", inversedBy="bars") */
    public $foo;
}