<?php

namespace Rector\Tests\Privatization\Rector\Class_\RepeatedLiteralToClassConstantRector\Fixture;

class UseExistsSameValue
{
    /**
     * @var string
     */
    private const REQUIRES = 'requires';

    public function run($key, $items)
    {
        if ($key === 'requires') {
            return $items['requires'];
        }

        return $items['requires'];
    }
}

?>
-----
<?php

namespace Rector\Tests\Privatization\Rector\Class_\RepeatedLiteralToClassConstantRector\Fixture;

class UseExistsSameValue
{
    /**
     * @var string
     */
    private const REQUIRES = 'requires';

    public function run($key, $items)
    {
        if ($key === self::REQUIRES) {
            return $items[self::REQUIRES];
        }

        return $items[self::REQUIRES];
    }
}

?>
