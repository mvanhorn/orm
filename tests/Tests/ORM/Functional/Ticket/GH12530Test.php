<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;
use Doctrine\Tests\OrmFunctionalTestCase;

final class GH12530Test extends OrmFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchemaForModels(
            GH12530AbstractNode::class,
            GH12530AbstractButton::class,
            GH12530ChildButton::class,
            GH12530OtherChildButton::class,
            GH12530FilteredEntity::class,
        );

        $this->_em->getConfiguration()->addFilter(GH12530Filter::class, GH12530Filter::class);
    }

    public function testIndexedCollectionHydrationAfterUnrelatedFilterChanges(): void
    {
        $childButton = new GH12530ChildButton(10);
        $childButton->addChild(new GH12530ChildButton(11));

        $otherChildButton = new GH12530OtherChildButton(20);
        $otherChildButton->addChild(new GH12530OtherChildButton(21));

        $this->_em->persist($childButton);
        $this->_em->persist($otherChildButton);
        $this->_em->persist(new GH12530FilteredEntity('included'));
        $this->_em->persist(new GH12530FilteredEntity('excluded'));
        $this->_em->flush();
        $this->_em->clear();

        $childButton = $this->_em->getRepository(GH12530ChildButton::class)->findOneBy(['parent' => null]);
        self::assertInstanceOf(GH12530ChildButton::class, $childButton);
        self::assertSame([11], $childButton->getChildren()->getKeys());
        self::assertContainsOnlyInstancesOf(GH12530ChildButton::class, $childButton->getChildren());

        $this->_em->clear();
        $filter = $this->_em->getFilters()->enable(GH12530Filter::class);
        $filter->setParameter('category', 'missing');

        self::assertSame([], $this->_em->getRepository(GH12530FilteredEntity::class)->findAll());

        $otherChildButton = $this->_em->getRepository(GH12530OtherChildButton::class)->findOneBy(['parent' => null]);
        self::assertInstanceOf(GH12530OtherChildButton::class, $otherChildButton);
        self::assertSame([21], $otherChildButton->getChildren()->getKeys());
        self::assertContainsOnlyInstancesOf(GH12530OtherChildButton::class, $otherChildButton->getChildren());

        $this->_em->clear();
        $filter->setParameter('category', 'included');

        self::assertCount(1, $this->_em->getRepository(GH12530FilteredEntity::class)->findAll());

        $otherChildButton = $this->_em->getRepository(GH12530OtherChildButton::class)->findOneBy(['parent' => null]);
        self::assertInstanceOf(GH12530OtherChildButton::class, $otherChildButton);
        self::assertSame([21], $otherChildButton->getChildren()->getKeys());
        self::assertContainsOnlyInstancesOf(GH12530OtherChildButton::class, $otherChildButton->getChildren());

        $this->_em->clear();

        $otherChildButton = $this->_em->getRepository(GH12530OtherChildButton::class)->findOneBy(['parent' => null]);
        self::assertInstanceOf(GH12530OtherChildButton::class, $otherChildButton);
        self::assertSame([21], $otherChildButton->getChildren()->getKeys());
        self::assertContainsOnlyInstancesOf(GH12530OtherChildButton::class, $otherChildButton->getChildren());
    }
}

#[ORM\Entity]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'discr', type: 'string')]
#[ORM\DiscriminatorMap([
    'child' => GH12530ChildButton::class,
    'other-child' => GH12530OtherChildButton::class,
])]
abstract class GH12530AbstractNode
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;
}

#[ORM\Entity]
abstract class GH12530AbstractButton extends GH12530AbstractNode
{
    #[ORM\Column(type: 'integer')]
    private int $sortIndex;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    private self|null $parent = null;

    /** @var Collection<int, self> */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent', indexBy: 'sortIndex', cascade: ['persist'])]
    private Collection $children;

    public function __construct(int $sortIndex)
    {
        $this->sortIndex = $sortIndex;
        $this->children  = new ArrayCollection();
    }

    public function addChild(self $child): void
    {
        $child->parent = $this;
        $this->children->set($child->sortIndex, $child);
    }

    /** @return Collection<int, self> */
    public function getChildren(): Collection
    {
        return $this->children;
    }
}

#[ORM\Entity]
final class GH12530ChildButton extends GH12530AbstractButton
{
}

#[ORM\Entity]
final class GH12530OtherChildButton extends GH12530AbstractButton
{
}

#[ORM\Entity]
final class GH12530FilteredEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    public function __construct(
        #[ORM\Column(type: 'string')]
        private string $category,
    ) {
    }
}

final class GH12530Filter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if ($targetEntity->name !== GH12530FilteredEntity::class) {
            return '';
        }

        return $targetTableAlias . '.category = ' . $this->getParameter('category');
    }
}
