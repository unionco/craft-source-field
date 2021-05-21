<?php

namespace unionco\sourcefield\models;

use Craft;
use craft\base\Model;
use craft\elements\Entry;
use craft\commerce\elements\Product;
use craft\elements\Category;
use craft\elements\db\ElementQueryInterface;
use craft\elements\Tag;

/**
 * FieldGroup model class.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0.0
 */
class SourceOption extends Model
{
    /**
     * @var array|null
     */
    public $selection;

    /**
     * @var string|null
     */
    public $source;

    // Public Methods
    // =========================================================================

    /**
     * Constructor
     *
     * @param string|null $label
     * @param string|null $value
     * @param bool $selected
     */
    public function __construct(array $selection = [], string $source = '')
    {
        $this->selection = $selection;
        $this->source = $source;
    }

    /**
     * Get all selected sources
     *
     * @return array|null
     */
    public function all(): ?array
    {
        if ($this->selection) {
            return $this->selection;
        }
        return null;
    }

    /**
     * Get single selected source
     *
     * @return array
     */
    public function one():? array
    {
        if ($this->selection) {
            return $this->selection[0];
        }
        return null;
    }

    /**
     * Get query interface
     *
     * @param array
     * @return ElementQueryInterface
     */
    public function query($criteria = []): ElementQueryInterface
    {
        $query = $this->source::find();

        switch ($this->source) {
            case Entry::class:
                $criteria = array_merge($criteria, ['section' => $this->_getSourceHandles()]);
                break;
            case Category::class:
            case Tag::class:
                $criteria = array_merge($criteria, ['group' => $this->_getSourceHandles()]);
                break;
            case Product::class:
                $criteria = array_merge($criteria, ['type' => $this->_getSourceHandles()]);
                break;
        }

        Craft::configure($query, $criteria);

        return $query;
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    private function _getSourceHandles(): array
    {
        return array_map(
            /**
             * Return handle only
             * @param array
             * @return string
             */
            function(array $selection) {
                return $selection['handle'];
            },
            $this->selection
        );
    }
}
