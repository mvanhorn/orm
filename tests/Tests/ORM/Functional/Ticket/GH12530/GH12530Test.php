<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket\GH12530;

use Doctrine\Tests\OrmFunctionalTestCase;

final class GH12530Test extends OrmFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchemaForModels(
            AbstractNode::class,
            AbstractButton::class,
            ChildButton::class,
            OtherChildButton::class,
        );
    }

    public function testIndexByAliasIsPreservedWhenFilterRebuildsJoinedInheritanceSelect(): void
    {
        $childButton      = new ChildButton(10);
        $childButtonChild = new ChildButton(11);
        $childButton->addChild($childButtonChild);

        $otherChildButton      = new OtherChildButton(20);
        $otherChildButtonChild = new OtherChildButton(21);
        $otherChildButton->addChild($otherChildButtonChild);

        $this->_em->persist($childButton);
        $this->_em->persist($otherChildButton);
        $this->_em->flush();
        $this->_em->clear();

        $loadedChildButtons = $this->_em->getRepository(ChildButton::class)->findBy(['parent' => null]);

        self::assertCount(1, $loadedChildButtons);
        self::assertSame([11], $loadedChildButtons[0]->children->getKeys());
        self::assertSame(11, $loadedChildButtons[0]->children[11]->sortIndex);

        $this->_em->clear();
        $this->_em->getConfiguration()->addFilter(UnrelatedSQLFilter::class, UnrelatedSQLFilter::class);
        $this->_em->getFilters()->enable(UnrelatedSQLFilter::class);

        $loadedOtherChildButtons = $this->_em->getRepository(OtherChildButton::class)->findBy(['parent' => null]);

        self::assertCount(1, $loadedOtherChildButtons);
        self::assertSame([21], $loadedOtherChildButtons[0]->children->getKeys());
        self::assertSame(21, $loadedOtherChildButtons[0]->children[21]->sortIndex);
    }
}
