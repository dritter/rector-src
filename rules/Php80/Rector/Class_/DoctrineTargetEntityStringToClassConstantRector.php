<?php

declare(strict_types=1);

namespace Rector\Php80\Rector\Class_;

use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Property;
use Rector\BetterPhpDocParser\PhpDoc\DoctrineAnnotationTagValueNode;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfo;
use Rector\BetterPhpDocParser\PhpDocParser\ClassAnnotationMatcher;
use Rector\Core\Rector\AbstractRector;
use Rector\Core\ValueObject\PhpVersionFeature;
use Rector\Doctrine\NodeAnalyzer\AttributeFinder;
use Rector\VersionBonding\Contract\MinPhpVersionInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @see \Rector\Tests\Php80\Rector\Class_\DoctrineTargetEntityStringToClassConstantRector\DoctrineTargetEntityStringToClassConstantRectorTest
 */
final class DoctrineTargetEntityStringToClassConstantRector extends AbstractRector implements MinPhpVersionInterface
{
    private const VALID_DOCTRINE_CLASSES = [
        'Doctrine\ORM\Mapping\OneToMany',
        'Doctrine\ORM\Mapping\ManyToOne',
        'Doctrine\ORM\Mapping\OneToOne',
        'Doctrine\ORM\Mapping\ManyToMany',
        'Doctrine\ORM\Mapping\Embedded',
    ];

    private const ATTRIBUTE_NAME__TARGET_ENTITY = 'targetEntity';

    private const ATTRIBUTE_NAME__CLASS = 'class';

    public function __construct(
        private readonly ClassAnnotationMatcher $classAnnotationMatcher,
        private readonly AttributeFinder $attributeFinder
    ) {
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert targetEntities defined as String to <class>::class Constants in Doctrine Entities.',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
final class SomeClass
{
    /**
     * @ORM\OneToMany(targetEntity="AnotherClass")
     */
    private readonly ?Collection $items;

    #[ORM\ManyToOne(targetEntity: "AnotherClass")]
    private readonly ?Collection $items2;
}
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
final class SomeClass
{
    /**
     * @ORM\OneToMany(targetEntity=\Rector\Tests\Php80\Rector\Class_\DoctrineTargetEntityStringToClassConstantRector\Source\AnotherClass::class)
     */
    private readonly ?Collection $items;

    #[ORM\ManyToOne(targetEntity: \Rector\Tests\Php80\Rector\Class_\DoctrineTargetEntityStringToClassConstantRector\Source\AnotherClass::class)]
    private readonly ?Collection $items2;
}
CODE_SAMPLE
                ),

            ]
        );
    }

    public function provideMinPhpVersion(): int
    {
        // The minimum Version is PHP 5.5, because we need classname constants,
        // and support Annotations as well as Attributes.
//        return PhpVersionFeature::ATTRIBUTES;
        return PhpVersionFeature::CLASSNAME_CONSTANT;
    }

    public function getNodeTypes(): array
    {
        return [Property::class];
    }

    public function refactor(Node $node): ?Node
    {
        $phpDocInfo = $this->phpDocInfoFactory->createFromNode($node);
        if ($phpDocInfo) {
            $this->changeTypeInAnnotationTypes($node, $phpDocInfo);
        }

        return $this->changeTypeInAttributeTypes($node, $phpDocInfo->hasChanged());
    }

    protected function changeTypeInAttributeTypes(Node $node, bool $hasChanged): ?Node
    {
        $attribute = $this->attributeFinder->findAttributeByClasses($node, self::VALID_DOCTRINE_CLASSES);

        if ($attribute === null) {
            return $hasChanged ? $node : null;
        }

        foreach ($attribute->args as $arg) {
            $argName = $arg->name;
            if (! $argName instanceof Identifier) {
                continue;
            }

            if (! $this->isName($argName, self::ATTRIBUTE_NAME__TARGET_ENTITY)) {
                continue;
            }

            $value = $this->valueResolver->getValue($arg->value);
            $fullyQualified = $this->classAnnotationMatcher->resolveTagFullyQualifiedName($value, $node);

            if ($fullyQualified === $value) {
                continue;
            }

            $arg->value = $this->nodeFactory->createClassConstFetch($fullyQualified, 'class');

            return $node;
        }

        return $hasChanged ? $node : null;
    }

    private function changeTypeInAnnotationTypes(Node $node, PhpDocInfo $phpDocInfo): void
    {
        $doctrineAnnotationTagValueNode = $phpDocInfo->getByAnnotationClasses(self::VALID_DOCTRINE_CLASSES);

        if (! $doctrineAnnotationTagValueNode instanceof DoctrineAnnotationTagValueNode) {
            return;
        }

        $this->processDoctrineToMany($doctrineAnnotationTagValueNode, $node);
    }

    private function processDoctrineToMany(
        DoctrineAnnotationTagValueNode $doctrineAnnotationTagValueNode,
        Node $node
    ): void {
        $key = $doctrineAnnotationTagValueNode->hasClassName(
            'Doctrine\ORM\Mapping\Embedded'
        ) ? self::ATTRIBUTE_NAME__CLASS : self::ATTRIBUTE_NAME__TARGET_ENTITY;

        $targetEntity = $doctrineAnnotationTagValueNode->getValueWithoutQuotes($key);
        if ($targetEntity === null) {
            return;
        }

        // resolve to FQN
        $tagFullyQualifiedName = $this->classAnnotationMatcher->resolveTagFullyQualifiedName($targetEntity, $node);

        if ($tagFullyQualifiedName === $targetEntity) {
            return;
        }

        $doctrineAnnotationTagValueNode->removeValue($key);
        $doctrineAnnotationTagValueNode->values[$key] = '\\' . $tagFullyQualifiedName . '::class';
    }
}
