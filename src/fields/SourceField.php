<?php

namespace unionco\sourcefield\fields;

use Craft;
use craft\base\Field;
use craft\elements\Tag;
use craft\helpers\Json;
use craft\elements\Entry;
use craft\models\Section;
use craft\commerce\Plugin;
use craft\models\TagGroup;
use craft\elements\Category;
use craft\models\CategoryGroup;
use craft\base\ElementInterface;
use craft\commerce\elements\Product;
use craft\commerce\models\ProductType;
use craft\base\PreviewableFieldInterface;
use craft\helpers\StringHelper;
use unionco\sourcefield\models\SourceOption;

class SourceField extends Field implements PreviewableFieldInterface
{
    /** @var integer */
    public $limit;

    /** @var array */
    public $entrySources = [];

    /** @var array */
    public $categorySources = [];

    /** @var array */
    public $tagSources = [];

    /** @var array */
    public $commerceSources = [];

    /** @var string */
    public $sourceType = Entry::class;

    private $sourceTypeMap = [
        Entry::class => 'section',
        Category::class => 'category',
        Tag::class => 'tag',
        Product::class => 'commerce',
    ];

    /** @inheritdoc */
    public static function displayName(): string
    {
        return Craft::t('app', 'Sources');
    }

    /**
     * @inheritdoc
     */
    protected function optionsSettingLabel(): string
    {
        return Craft::t('app', 'Available Sources');
    }

    /**
     * @inheritdoc
     */
    public function serializeValue($value, ElementInterface $element = null)
    {
        $serialized = [];

        if ($value instanceof SourceOption) {
            $value = $value->selection;
        }

        if ($value && is_array($value)) {
            foreach ($value as $val) {
                $serialized[] = $this->sourceTypeMap[$this->sourceType] . ":" . $val['uid'];
            }

            return $serialized;
        }

        return parent::serializeValue($serialized, $element);
    }

    /**
     * @inheritdoc
     */
    public function normalizeValue($value, ElementInterface $element = null)
    {
        if (is_string($value) && ($value === '' ||
            strpos($value, '[') === 0 ||
            strpos($value, '{') === 0)) {
            $value = Json::decodeIfJson($value);
        } else if ($value === null && $this->isFresh($element)) {
            $value = [];
        }

        // Normalize to an array
        $values = (array) $value;
        $normalizedValues = [];
        foreach ($values as $key => $val) {
            $uid = explode(":", $val)[1];

            $selection = $this->getSelectionByUid($uid);

            if (!$selection) {
                continue;
            }

            $normalizedValues[] = [
                'name' => $selection->name,
                'handle' => $selection->handle,
                'uid' => $selection->uid,
                'source' => $this->sourceTypeMap[$this->sourceType]
            ];
        }

        return new SourceOption($normalizedValues, $this->sourceType);
    }

    /**
     * @inheritdoc
     */
    public function settingsAttributes(): array
    {
        $attributes = parent::settingsAttributes();

        $attributes[] = 'entrySources';
        $attributes[] = 'categorySources';
        $attributes[] = 'tagSources';
        $attributes[] = 'commerceSources';
        $attributes[] = 'sourceType';
        $attributes[] = 'limit';

        return $attributes;
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml()
    {
        $commerceInstalled = Craft::$app->getPlugins()->isPluginEnabled('commerce');

        return Craft::$app->getView()->renderTemplate('source-field/_fieldsettings', [
            'field' => $this,
            'sourceTypes' => $this->sourceTypes(),
            'commerceInstalled' => $commerceInstalled,
            'entryOptions' => $this->entryOptions(),
            'categoryOptions' => $this->categoryOptions(),
            'tagOptions' => $this->tagOptions(),
            'commerceOptions' => $commerceInstalled ? $this->commerceOptions() : []
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getInputHtml($value, ElementInterface $element = null): string
    {
        if ($value instanceof SourceOption) {
            $value = $value->selection;
        }

        $options = [];
        if ($this->sourceType === Entry::class) {
            if ($this->entrySources === "*") {
                $options = $this->entryOptions();
            } else {
                $options = array_map(
                    function($source) {
                        $uid = explode(":", $source)[1];
                        $section = $this->getSelectionByUid($uid);
                        return [
                            'label' => $section->name,
                            'value' => "section:{$section->uid}"
                        ];
                    },
                    $this->entrySources
                );
            }
        }

        if ($this->sourceType === Category::class) {
            if ($this->categorySources === "*") {
                $options = $this->categoryOptions();
            } else {
                $options = array_map(
                    function($source) {
                        $uid = explode(":", $source)[1];
                        $section = $this->getSelectionByUid($uid);
                        return [
                            'label' => $section->name,
                            'value' => "category:{$section->uid}"
                        ];
                    },
                    $this->categorySources
                );
            }
        }

        if ($this->sourceType === Tag::class) {
            if ($this->tagSources === "*") {
                $options = $this->tagOptions();
            } else {
                $options = array_map(
                    function($source) {
                        $uid = explode(":", $source)[1];
                        $section = $this->getSelectionByUid($uid);
                        return [
                            'label' => $section->name,
                            'value' => "tag:{$section->uid}"
                        ];
                    },
                    $this->tagSources
                );
            }
        }

        if ($this->sourceType === Product::class) {
            if ($this->commerceSources === "*") {
                $options = $this->commerceOptions();
            } else {
                $options = array_map(
                    function ($source) {
                        $uid = explode(":", $source)[1];
                        $productType = $this->getSelectionByUid($uid);
                        return [
                            'label' => $productType->name,
                            'value' => "commerce:{$productType->uid}"
                        ];
                    },
                    $this->commerceSources
                );
            }
        }

        if (!$this->limit || ($this->limit && $this->limit > 1)) {
            return Craft::$app->getView()
                ->renderTemplateMacro(
                    '_includes/forms',
                    'checkboxGroup',
                    [
                        [
                            'name' => $this->handle,
                            'values' => array_map(
                                function($val) {
                                    return $val['source'].":".$val['uid'];
                                },
                                $value
                            ),
                            'options' => $options,
                        ]
                    ]
                );
        }

        $value = $value ? $value[0] : null;
        return Craft::$app->getView()
            ->renderTemplateMacro(
                '_includes/forms',
                'radioGroupField',
                [
                    [
                        'name' => $this->handle,
                        'value' => $value ? $value['source'] . ":" . $value['uid'] : [],
                        'options' => $options,
                    ]
                ]
            );
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    protected function sourceTypes(): array
    {
        $sourceTypes = [
            [
                'label' => 'Entries',
                'value' => Entry::class
            ],
            [
                'label' => 'Categories',
                'value' => Category::class
            ],
            [
                'label' => 'Tags',
                'value' => Tag::class
            ],
        ];

        if (Craft::$app->getPlugins()->isPluginEnabled('commerce')) {
            $sourceTypes[] = [
                'label' => 'Products',
                'value' => Product::class
            ];
        }

        return $sourceTypes;
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    protected function entryOptions(): array
    {
        $options = [];

        /** @var Section[] */
        $sectionSources = Craft::$app
            ->getSections()
            ->getAllSections();

        // Remove singles
        $sectionSources = array_filter(
            $sectionSources,
            function (Section $s): bool {
                return $s->type != 'single';
            }
        );

        foreach ($sectionSources as $key => $section) {
            $options[] = [
                'label' => Craft::t('site', $section->name),
                'value' => "section:{$section->uid}"
            ];
        }

        return $options;
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    protected function categoryOptions(): array
    {
        $options = [];

        /** @var CategoryGroup[] */
        $categoryGroups = Craft::$app
            ->getCategories()
            ->getAllGroups();

        foreach ($categoryGroups as $group) {
            $options[] = [
                'label' => Craft::t('site', $group->name),
                'value' => "category:{$group->uid}"
            ];
        }

        return $options;
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    protected function tagOptions(): array
    {
        $options = [];

        /** @var TagGroup[] */
        $tagGroups = Craft::$app
            ->getTags()
            ->getAllTagGroups();

        foreach ($tagGroups as $group) {
            $options[] = [
                'label' => Craft::t('site', $group->name),
                'value' => "tag:{$group->uid}"
            ];
        }

        return $options;
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    protected function commerceOptions(): array
    {
        $options = [];

        /** @var ProductType[] */
        $productSources = Plugin::getInstance()
            ->getProductTypes()
            ->getAllProductTypes();

        foreach ($productSources as $key => $source) {
            $options[] = [
                'label' => Craft::t('site', $source->name),
                'value' => "commerce:{$source->uid}"
            ];
        }

        return $options;
    }

    /**
     * Undocumented function
     *
     * @param string $uid
     * @return mixed
     */
    private function getSelectionByUid(string $uid)
    {
        switch ($this->sourceType) {
            case Entry::class:
                return Craft::$app->getSections()->getSectionByUid($uid);
            case Category::class:
                return Craft::$app->getCategories()->getGroupByUid($uid);
            case Tag::class:
                return Craft::$app->getTags()->getTagGroupByUid($uid);
            case Product::class:
                if (Craft::$app->getPlugins()->isPluginEnabled('commerce')) {
                    return Plugin::getInstance()->getProductTypes()->getProductTypeByUid($uid);
                }
                break;
            default:
                return null;
        }
    }
}
