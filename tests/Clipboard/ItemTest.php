<?php

/**
 * This file is part of contao-community-alliance/dc-general.
 *
 * (c) 2013-2019 Contao Community Alliance.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    contao-community-alliance/dc-general
 * @author     David Molineus <david.molineus@netzmacht.de>
 * @author     Sven Baumann <baumann.sv@gmail.com>
 * @copyright  2013-2019 Contao Community Alliance.
 * @license    https://github.com/contao-community-alliance/dc-general/blob/master/LICENSE LGPL-3.0-or-later
 * @filesource
 */

namespace ContaoCommunityAlliance\DcGeneral\Test\Clipboard;

use ContaoCommunityAlliance\DcGeneral\Clipboard\Item;
use ContaoCommunityAlliance\DcGeneral\Clipboard\ItemInterface;
use ContaoCommunityAlliance\DcGeneral\Clipboard\UnsavedItem;
use ContaoCommunityAlliance\DcGeneral\Data\ModelId;
use ContaoCommunityAlliance\DcGeneral\Data\ModelIdInterface;
use ContaoCommunityAlliance\DcGeneral\Test\TestCase;

/**
 * Test for Item
 *
 * @covers \ContaoCommunityAlliance\DcGeneral\Clipboard\Item::equals
 */
class ItemTest extends TestCase
{
    const TEST_PROVIDER = 'dummy-provider';

    /**
     * Test the the Item requires an valid model id.
     *
     * @return void
     */
    public function testItemRequiresModelId()
    {
        $this->expectException('InvalidArgumentException');

        new Item(ItemInterface::CREATE, null, null);
    }

    /**
     * Run the tests with a parent id.
     *
     * @param ModelIdInterface|null $parentId Optional parent id.
     */
    private function runAssertsWithParentId($parentId)
    {
        $item        = new MockedAbstractItem(ItemInterface::CREATE, $parentId, self::TEST_PROVIDER);
        $unsavedItem = new UnsavedItem(ItemInterface::CREATE, $parentId, self::TEST_PROVIDER);
        $modelId     = new ModelId(self::TEST_PROVIDER, 3);
        $item2       = new Item(ItemInterface::CREATE, $parentId, $modelId);

        // Compare unsaved item and normal one.
        self::assertEquals(false, $item2->equals($unsavedItem));

        // Test item with provider name only
        self::assertEquals(true, $item->equals($unsavedItem));
        self::assertEquals(false, $item->equals($item2));

        // Test item with model id.
        $item = new MockedAbstractItem(ItemInterface::CREATE, $parentId, $modelId);

        self::assertEquals(false, $item->equals($unsavedItem));
        self::assertEquals(true, $item->equals($item2));

        // Test different actions.
        $item = new MockedAbstractItem(ItemInterface::CUT, $parentId, $modelId);
        self::assertEquals(false, $item->equals($unsavedItem));
        self::assertEquals(false, $item->equals($item2));
    }


    /**
     * Test the comparing.
     *
     * @return void
     */
    public function testCompare()
    {
        // Test without a parent id.
        $this->runAssertsWithParentId(null);

        // Test item with parent id.
        $parentId = new ModelId('dummy-parent', 3);
        $this->runAssertsWithParentId($parentId);
    }
}
